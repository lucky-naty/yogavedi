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
use MauticPlugin\MauticTelegramBotsBundle\Helper\TelegramBotApiHelper;
use MauticPlugin\MauticTelegramBotsBundle\Model\BotModel;
use MauticPlugin\MauticTelegramBotsBundle\Repository\BotRepository;
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
        $repository = $this->em->getRepository(\MauticPlugin\MauticTelegramBotsBundle\Entity\Bot::class);

        // Получаем список ботов (используем стандартный метод Mautic для пагинации)
        $bots = $model->getList($request, $page);

        // Для каждого бота вычисляем реальное количество подписчиков
        foreach ($bots as $bot) {
            // Мы добавляем временное свойство в объект, чтобы шаблон мог его прочитать
            $bot->dynamicSubscribersCount = $bot->getRealSubscribersCount($repository);
        }

        // Передаем данные в шаблон
        return $this->render($this->getTemplateBase() . '/list.html.twig', [
            'bots' => $bots,
            'page' => $page,
            'total' => $model->getTotal(),
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

        $meResult = $this->apiHelper->getMe($entity->getToken());
        if (!($meResult['ok'] ?? false)) {
            $this->addFlashMessage('Invalid token: ' . ($meResult['description'] ?? 'unknown'), [], 'error');
            return $this->redirect($indexUrl);
        }

        $botUsername = $meResult['result']['username'] ?? '';
        $entity->setBotUsername('@' . $botUsername);
        $webhookUrl = $request->getSchemeAndHttpHost() . '/telegram/webhook/' . $entity->getToken();
        $entity->setWebhookUrl($webhookUrl);
        $result = $this->apiHelper->setWebhook($entity->getToken(), $webhookUrl);

        if ($result['ok'] ?? false) {
            $entity->setWebhookRegisteredAt(new \DateTime());
            $model->saveEntity($entity);
            $this->addFlashMessage('Webhook registered for @' . $botUsername);
        } else {
            $this->addFlashMessage($result['description'] ?? 'Failed', [], 'error');
        }

        return $this->redirect($indexUrl);
    }
}