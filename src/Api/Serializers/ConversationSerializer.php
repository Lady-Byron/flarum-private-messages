<?php

namespace Neoncube\FlarumPrivateMessages\Api\Serializers;

use Flarum\Api\Serializer\AbstractSerializer;
use Neoncube\FlarumPrivateMessages\Conversation;

class ConversationSerializer extends AbstractSerializer
{
    protected $type = 'conversations';

    protected function getDefaultAttributes($conversation)
    {
        if (!($conversation instanceof Conversation)) {
            throw new \InvalidArgumentException(
                get_class($this) . ' can only serialize instances of ' . Conversation::class
            );
        }

        return [
            // 容错：NULL/'' 时返回空数组，避免 PHP 8.1+ deprecation & headers already sent
            'status' => json_decode($conversation->status ?? '[]', true) ?? [],

            'createdAt' => $this->formatDate($conversation->created_at),
            // 可选修正：优先使用 updated_at
            'updatedAt' => $this->formatDate($conversation->updated_at ?? $conversation->created_at),

            'totalMessages' => $conversation->total_messages,
            'notNew' => (bool) $conversation->notNew,

            // 原写法保留（最小改动）；如需性能更好可改为 ->where('is_seen', false)->count()
            'unReadCount' => $conversation->messages()
                ->get()
                ->filter(function ($message) {
                    if (!$message->is_seen) {
                        return $message;
                    }
                })
                ->count()
        ];
    }

    protected function messages($conversation)
    {
        return $this->hasMany($conversation, MessageSerializer::class);
    }

    protected function recipients($conversation)
    {
        return $this->hasMany($conversation, ConversationRecipientSerializer::class);
    }
}
