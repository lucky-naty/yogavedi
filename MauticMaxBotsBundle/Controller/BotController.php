<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Controller\AbstractStandardFormController;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\FormBundle\Helper\FormFieldHelper;
use MauticPlugin\MauticMaxBotsBundle\Entity\Bot;
use MauticPlugin\MauticMaxBotsBundle\Model\BotModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

final class BotController extends AbstractStandardFormController
{
    private const PAGE_LIMIT = 25;

    public function __construct(
        FormFactoryInterface $formFactory,
        FormFieldHelper $fieldHelper,
        ManagerRegistry $doctrine,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $dispatcher,
        Translator $translator,
        FlashBag $flashBag,
        private RequestStack $maxRequestStack,
        CorePermissions $security,
    ) {
        parent::__construct(
            $formFactory,
            $fieldHelper,
            $doctrine,
            $modelFactory,
            $userHelper,
            $coreParametersHelper,
            $dispatcher,
            $translator,
            $flashBag,
            $maxRequestStack,
            $security
        );
    }

    public function indexAction(int $page = 1): Response
    {
        /** @var BotModel $model */
        $model = $this->getModel($this->getModelName());
        $page = max(1, $page);
        $offset = ($page - 1) * self::PAGE_LIMIT;
        $repository = $model->getRepository();

        $items = $repository->findBy([], ['id' => 'DESC'], self::PAGE_LIMIT, $offset);
        $subscriberCounts = $this->getSubscriberCounts(array_map(static fn (Bot $bot): int => (int) $bot->getId(), $items));
        $total = method_exists($repository, 'count') ? (int) $repository->count([]) : count($items);

        return $this->delegateView([
            'viewParameters' => [
                'items'            => $items,
                'page'             => $page,
                'total'            => $total,
                'limit'            => self::PAGE_LIMIT,
                'subscriberCounts' => $subscriberCounts,
            ],
            'contentTemplate' => $this->getTemplateBase().'/list.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_max_bots_index',
                'mauticContent' => 'maxBot',
                'route'         => $this->generateUrl('mautic_max_bots_index', ['page' => $page]),
            ],
        ]);
    }

    public function executeAction(string $objectAction, int $objectId = 0): Response
    {
        $request = $this->getCurrentRequest();

        return match ($objectAction) {
            'new' => $this->newStandard($request),
            'edit' => $this->editStandard($request, $objectId),
            'delete' => $this->deleteStandard($request, $objectId),
            default => $this->redirectToIndexWithError('mautic.max.bots.flash.unsupported_action'),
        };
    }

    public function registerWebhookAction(int $id): Response
    {
        /** @var BotModel $model */
        $model = $this->getModel($this->getModelName());
        /** @var Bot|null $bot */
        $bot = $model->getEntity($id);

        if (!$bot instanceof Bot) {
            return $this->redirectToIndexWithError('mautic.max.bots.flash.not_found');
        }

        $connectionResult = $model->checkConnection($bot);
        if (!($connectionResult['ok'] ?? false)) {
            $bot
                ->setWebhookStatus('error')
                ->setLastWebhookError((string) ($connectionResult['description'] ?? 'Connection check failed.'));

            $model->saveEntity($bot);

            return $this->redirectToIndexWithError(
                'mautic.max.bots.flash.connection_failed',
                ['%message%' => (string) ($connectionResult['description'] ?? 'Connection check failed.')]
            );
        }

        $botUsername = $this->extractBotUsername($connectionResult);
        if ('' !== $botUsername) {
            $bot->setUsername($botUsername);
        }

        $displayName = $this->extractDisplayName($connectionResult);
        if ('' !== $displayName) {
            $bot->setDisplayName($displayName);
        }

        $registerResult = $model->registerWebhook($bot);
        if ($registerResult['ok'] ?? false) {
            $bot
                ->setWebhookStatus('registered')
                ->setLastWebhookError(null)
                ->setLastWebhookSyncedAt(new \DateTimeImmutable());

            $model->saveEntity($bot);
            $this->addFlashMessage('mautic.max.bots.flash.webhook_registered', [
                '%name%' => '' !== $bot->getName() ? $bot->getName() : $bot->getDisplayName(),
            ]);

            return new RedirectResponse($this->generateUrl('mautic_max_bots_index'));
        }

        $bot
            ->setWebhookStatus('error')
            ->setLastWebhookError((string) ($registerResult['description'] ?? 'Webhook registration failed.'));

        $model->saveEntity($bot);

        return $this->redirectToIndexWithError(
            'mautic.max.bots.flash.webhook_failed',
            ['%message%' => (string) ($registerResult['description'] ?? 'Webhook registration failed.')]
        );
    }

    protected function getModelName(): string
    {
        return 'maxBots.bot';
    }

    protected function getTemplateBase(): string
    {
        return '@MauticMaxBots/Bot';
    }

    protected function getRouteBase(): string
    {
        return 'max_bots';
    }

    /**
     * @param int[] $botIds
     *
     * @return array<int, int>
     */
    private function getSubscriberCounts(array $botIds): array
    {
        if ([] === $botIds) {
            return [];
        }

        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT bot_id, COUNT(*) AS subscriber_count
             FROM max_subscriptions
             WHERE is_active = 1 AND bot_id IN (:botIds)
             GROUP BY bot_id',
            ['botIds' => $botIds],
            ['botIds' => \Doctrine\DBAL\ArrayParameterType::INTEGER]
        );

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['bot_id']] = (int) $row['subscriber_count'];
        }

        return $counts;
    }

    private function getCurrentRequest(): Request
    {
        return $this->maxRequestStack->getCurrentRequest() ?? Request::create('/');
    }

    /**
     * @param array<string, mixed> $result
     */
    private function extractBotUsername(array $result): string
    {
        $candidate = $result['username']
            ?? $result['result']['username']
            ?? '';

        return ltrim(trim((string) $candidate), '@');
    }

    /**
     * @param array<string, mixed> $result
     */
    private function extractDisplayName(array $result): string
    {
        $candidate = $result['display_name']
            ?? $result['name']
            ?? $result['result']['display_name']
            ?? $result['result']['name']
            ?? '';

        return trim((string) $candidate);
    }

    /**
     * @param array<string, string> $parameters
     */
    private function redirectToIndexWithError(string $message, array $parameters = []): RedirectResponse
    {
        $this->addFlashMessage($message, $parameters, 'error');

        return new RedirectResponse($this->generateUrl('mautic_max_bots_index'));
    }
}
