<?php

declare(strict_types=1);

namespace MauticPlugin\MauticMaxBotsBundle\Helper;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\MauticMaxBotsBundle\Entity\MaxSubscription;

final class ContactManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LeadModel $leadModel,
    ) {
    }

    public function findLeadIdByPhone(string $phoneNumber): ?int
    {
        $phoneNumber = $this->normalizePhoneNumber($phoneNumber);
        if ('' === $phoneNumber) {
            return null;
        }

        $leadId = $this->entityManager->getConnection()->fetchOne(
            'SELECT id FROM leads WHERE phone = :phone LIMIT 1',
            ['phone' => $phoneNumber]
        );

        if (false === $leadId || null === $leadId || '' === (string) $leadId) {
            return null;
        }

        return (int) $leadId;
    }

    public function createOrUpdateLeadFromSubscription(MaxSubscription $subscription): int
    {
        $lead = $this->resolveLead($subscription);
        $this->applySubscriptionFields($lead, $subscription);
        $this->leadModel->saveEntity($lead);

        $leadId = (int) $lead->getId();
        if (0 >= $leadId) {
            throw new \RuntimeException('Lead was not saved.');
        }

        return $leadId;
    }

    private function resolveLead(MaxSubscription $subscription): Lead
    {
        $leadId = $subscription->getLeadId();
        if (null !== $leadId) {
            $lead = $this->leadModel->getEntity($leadId);
            if ($lead instanceof Lead) {
                return $lead;
            }
        }

        $phoneNumber = $this->normalizePhoneNumber((string) ($subscription->getPhoneNumber() ?? ''));
        if ('' === $phoneNumber) {
            throw new \InvalidArgumentException('Subscription phone number is required.');
        }

        $foundLeadId = $this->findLeadIdByPhone($phoneNumber);
        if (null !== $foundLeadId) {
            $lead = $this->leadModel->getEntity($foundLeadId);
            if ($lead instanceof Lead) {
                return $lead;
            }
        }

        return new Lead();
    }

    private function applySubscriptionFields(Lead $lead, MaxSubscription $subscription): void
    {
        $phoneNumber = $this->normalizePhoneNumber((string) ($subscription->getPhoneNumber() ?? ''));

        $fields = array_filter([
            'phone' => $phoneNumber,
            'firstname' => $subscription->getFirstName(),
            'lastname' => $subscription->getLastName(),
        ], static fn ($value): bool => '' !== trim((string) $value));

        if ([] !== $fields) {
            $this->leadModel->setFieldValues($lead, $fields, false);
        }
    }

    private function normalizePhoneNumber(string $phoneNumber): string
    {
        $phoneNumber = trim($phoneNumber);
        if ('' === $phoneNumber) {
            return '';
        }

        $normalized = preg_replace('/[^\d+]/', '', $phoneNumber) ?? '';
        $normalized = preg_replace('/(?!^)\+/', '', $normalized) ?? $normalized;

        return $normalized;
    }
}
