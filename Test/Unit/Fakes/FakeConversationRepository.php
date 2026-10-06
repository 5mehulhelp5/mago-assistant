<?php
/**
 * Copyright © Mago Assistant
 */
declare(strict_types=1);

namespace MagoAssistant\Mago\Test\Unit\Fakes;

use MagoAssistant\Mago\Api\ConversationRepositoryInterface;
use MagoAssistant\Mago\Model\Conversation\ConversationNotFoundException;

/**
 * An in-memory conversation store holding the conversations and messages a test starts with.
 */
final class FakeConversationRepository implements ConversationRepositoryInterface
{
    /**
     * @param array<int, array<string, mixed>> $conversations Keyed by conversation id
     * @param array<int, list<array<string, mixed>>> $messages Keyed by conversation id
     */
    public function __construct(
        private array $conversations = [],
        private array $messages = []
    ) {
    }

    public function create(int $adminUserId, string $title = 'New Chat'): int
    {
        $conversationId = count($this->conversations) + 1;
        $this->conversations[$conversationId] = [
            'entity_id' => $conversationId,
            'admin_user_id' => $adminUserId,
            'title' => $title,
        ];

        return $conversationId;
    }

    public function updateTitle(int $conversationId, string $title): void
    {
        $this->conversations[$conversationId]['title'] = $title;
    }

    public function getById(int $conversationId): array
    {
        return $this->conversations[$conversationId]
            ?? throw new ConversationNotFoundException('Conversation not found: ' . $conversationId);
    }

    public function getByIdForUser(int $conversationId, int $adminUserId): array
    {
        $conversation = $this->getById($conversationId);
        if ((int)($conversation['admin_user_id'] ?? 0) !== $adminUserId) {
            throw new ConversationNotFoundException('Conversation not found: ' . $conversationId);
        }

        return $conversation;
    }

    public function getListByUser(int $adminUserId): array
    {
        return array_values(array_filter(
            $this->conversations,
            static fn (array $conversation): bool => (int)($conversation['admin_user_id'] ?? 0) === $adminUserId
        ));
    }

    public function delete(int $conversationId, ?int $adminUserId = null): void
    {
        unset($this->conversations[$conversationId], $this->messages[$conversationId]);
    }

    public function addMessage(
        int $conversationId,
        string $role,
        string $content,
        ?array $toolCalls = null,
        bool $pendingConfirmation = false,
        ?string $toolCallId = null
    ): int {
        $messageId = count($this->messages[$conversationId] ?? []) + 1;
        $this->messages[$conversationId][] = [
            'entity_id' => $messageId,
            'role' => $role,
            'content' => $content,
            'tool_calls' => $toolCalls,
            'pending_confirmation' => (int)$pendingConfirmation,
        ];

        return $messageId;
    }

    public function getMessages(int $conversationId): array
    {
        return $this->messages[$conversationId] ?? [];
    }

    public function getMessageById(int $messageId): array
    {
        foreach ($this->messages as $messages) {
            foreach ($messages as $message) {
                if ((int)$message['entity_id'] === $messageId) {
                    return $message;
                }
            }
        }

        throw new ConversationNotFoundException('Message not found: ' . $messageId);
    }

    public function getMessageForUser(int $messageId, int $adminUserId): array
    {
        return $this->getMessageById($messageId);
    }

    public function resolveConfirmation(
        int $messageId,
        bool $confirmed,
        ?int $adminUserId = null,
        ?array $toolCalls = null
    ): void {
    }
}
