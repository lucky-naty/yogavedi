<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Controller\AbstractStandardFormController;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\FormBundle\Helper\FormFieldHelper;
use MauticPlugin\MauticTelegramBotsBundle\Entity\BotRepository;
use MauticPlugin\MauticTelegramBotsBundle\Helper\TelegramBotApiHelper;
use MauticPlugin\MauticTelegramBotsBundle\Helper\TokenCryptoHelper;
use MauticPlugin\MauticTelegramBotsBundle\Model\BotModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class BotController extends AbstractStandardFormController
{
    public function __construct(
        private TelegramBotApiHelper $apiHelper,
        FormFactoryInterface $formFactory,
        FormFieldHelper $fieldHelper,
        ManagerRegistry $doctrine,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $dispatcher,
        Translator $translator,
        FlashBag $flashBag,
        RequestStack $requestStack,
        CorePermissions $security,
    ) {
        parent::__construct($formFactory, $fieldHelper, $doctrine, $modelFactory, $userHelper, $coreParametersHelper, $dispatcher, $translator, $flashBag, $requestStack, $security);
    }

    protected function getModelName(): string
    {
        return 'telegramBots.bot';
    }

    protected function getTemplateBase(): string
    {
        return '@MauticTelegramBots/Bot';
    }

    protected function getRouteBase(): string
    {
        return 'telegram_bots';
    }

    public function indexAction(Request $request, int $page = 1): Response
    {
        /** @var BotModel $model */
        $model = $this->getModel('telegramBots.bot');
        /** @var BotRepository $repository */
        $repository = $model->getRepository();
        $bots = $model->getList($request, $page);

        foreach ($bots as $bot) {
            $bot->setDynamicSubscribersCount($bot->getRealSubscribersCount($repository));
        }

        return $this->delegateView([
            'viewParameters' => [
                'bots'  => $bots,
                'items' => $bots,
                'page'  => $page,
                'total' => $model->getTotal(),
            ],
            'contentTemplate' => $this->getTemplateBase() . '/list.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_telegram_bots_index',
                'mauticContent' => 'telegramBot',
                'route'         => $this->generateUrl('mautic_telegram_bots_index', ['page' => $page]),
            ],
        ]);
    }

    public function newAction(Request $request): Response
    {
        return parent::newStandard($request);
    }

    public function editAction(Request $request, int $objectId, bool $ignorePost = false): Response
    {
        return parent::editStandard($request, $objectId, $ignorePost);
    }

    public function deleteAction(Request $request, int $objectId): Response
    {
        return parent::deleteStandard($request, $objectId);
    }

    public function registerWebhookAction(Request $request, int $id): Response
    {
        /** @var BotModel $model */
        $model  = $this->getModel('telegramBots.bot');
        $entity = $model->getEntity($id);
        $indexUrl = $this->generateUrl('mautic_telegram_bots_index');

        if (!$entity) {
            $this->addFlashMessage('Bot not found', [], 'error');
            return $this->redirect($indexUrl);
        }

        $tokenCryptoHelper = new TokenCryptoHelper();
        $token = $tokenCryptoHelper->decryptIfNeeded($entity->getToken());

        if (!$this->isValidApiBaseUrl($entity->getApiBaseUrl())) {
            $this->addFlashMessage('Invalid Telegram API URL. Only HTTPS URLs are allowed.', [], 'error');
            return $this->redirect($indexUrl);
        }

        if (!$this->isValidWebhookBaseUrl($entity->getWebhookBaseUrl())) {
            $this->addFlashMessage('Invalid webhook base URL. Only HTTPS URLs are allowed.', [], 'error');
            return $this->redirect($indexUrl);
        }

        $meResult = $this->apiHelper->getMe($token, $entity->getApiBaseUrl());
        if (!($meResult['ok'] ?? false)) {
            $this->addFlashMessage('Invalid token: ' . ($meResult['description'] ?? 'unknown'), [], 'error');
            return $this->redirect($indexUrl);
        }

        $botUsername = $meResult['result']['username'] ?? '';
        $entity->setBotUsername('@' . ltrim((string) $botUsername, '@'));

        $webhookSecret = $entity->getWebhookSecret() ?: bin2hex(random_bytes(32));
        $entity->setWebhookSecret($webhookSecret);

        $webhookBaseUrl = $entity->getWebhookBaseUrl() ?: $request->getSchemeAndHttpHost();
        $webhookUrl = rtrim($webhookBaseUrl, '/') . '/telegram/webhook/' . $webhookSecret;
        $entity->setWebhookUrl($webhookUrl);
        $result = $this->apiHelper->setWebhook($token, $webhookUrl, '', $entity->getApiBaseUrl());

        if ($result['ok'] ?? false) {
            $entity->setWebhookRegisteredAt(new \DateTime());
            $model->saveEntity($entity);
            $this->addFlashMessage('Webhook registered for @' . $botUsername);
        } else {
            $this->addFlashMessage($result['description'] ?? 'Failed', [], 'error');
        }

        return $this->redirect($indexUrl);
    }

    private function isValidApiBaseUrl(string $url): bool
    {
        return '' === trim($url) || $this->isAllowedExternalHttpsUrl($url);
    }

    private function isValidWebhookBaseUrl(string $url): bool
    {
        return '' === trim($url) || $this->isAllowedExternalHttpsUrl($url);
    }

    private function isAllowedExternalHttpsUrl(string $url): bool
    {
        $parts = parse_url(trim($url));
        if (false === $parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        if ('https' !== strtolower((string) $parts['scheme'])) {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower((string) $parts['host']);
        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return false === filter_var(
                $host,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
        }

        return true;
    }
}
