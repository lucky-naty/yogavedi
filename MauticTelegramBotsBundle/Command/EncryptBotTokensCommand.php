<?php

declare(strict_types=1);

namespace MauticPlugin\MauticTelegramBotsBundle\Command;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\MauticTelegramBotsBundle\Entity\Bot;
use MauticPlugin\MauticTelegramBotsBundle\Helper\TokenCryptoHelper;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class EncryptBotTokensCommand extends Command
{
    protected static $defaultName = 'mautic:telegram-bots:encrypt-tokens';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
        parent::__construct('mautic:telegram-bots:encrypt-tokens');
    }

    protected function configure(): void
    {
        $this->setDescription('Encrypt existing plaintext Telegram bot tokens.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $crypto = new TokenCryptoHelper();
        $bots = $this->entityManager->getRepository(Bot::class)->findAll();
        $updated = 0;

        foreach ($bots as $bot) {
            if (!$bot instanceof Bot) {
                continue;
            }

            $currentToken = $bot->getToken();
            if ('' === trim($currentToken) || $crypto->isEncrypted($currentToken)) {
                continue;
            }

            $bot->setToken($crypto->encryptIfNeeded($currentToken));
            ++$updated;
        }

        if ($updated > 0) {
            $this->entityManager->flush();
        }

        $output->writeln(sprintf('Encrypted %d Telegram bot token(s).', $updated));

        return Command::SUCCESS;
    }
}
