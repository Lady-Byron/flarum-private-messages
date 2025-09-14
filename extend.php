<?php

namespace Neoncube\FlarumPrivateMessages;

use Flarum\Api\Controller;
use Flarum\Api\Serializer\CurrentUserSerializer;
use Flarum\Api\Serializer\ForumSerializer;
use Flarum\Extend;
use Flarum\User\User;
use Neoncube\FlarumPrivateMessages\Api\Controllers;
use Neoncube\FlarumPrivateMessages\Api\Serializers\ConversationRecipientSerializer;
use Neoncube\FlarumPrivateMessages\Api\Serializers\MessageSerializer;
use Neoncube\FlarumPrivateMessages\Notifications\NewPrivateMessageBlueprint;

return [
    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/resources/less/extension.less')
        ->route('/conversations/{id}', 'neoncube-private-messages.messages')
        ->route('/conversations', 'neoncube-private-messages.conversations'),

    new Extend\Locales(__DIR__ . '/resources/locale'),

    (new Extend\Routes('api'))
        ->get('/neoncube-private-messages/conversations', 'neoncube-private-messages.conversations.index', Controllers\ListConversationsController::class)
        ->get('/neoncube-private-messages/messages/{id}', 'neoncube-private-messages.messages.list', Controllers\ListMessagesController::class)
        ->post('/neoncube-private-messages/conversations', 'neoncube-private-messages.conversations.create', Controllers\CreateConversationController::class)
        ->post('/neoncube-private-messages/messages', 'neoncube-private-messages.messages.create', Controllers\CreateMessageController::class)
        ->post('/neoncube-private-messages/messages/typing', 'neoncube-private-messages.message.typing', Controllers\TypingPusherController::class)
        ->post('/neoncube-private-messages/messages/read', 'neoncube-private-messages.message.read', Controllers\ReadMessageController::class)
        ->delete('/neoncube-private-messages/messages{id}', 'neoncube-private-messages.messages.delete', Controllers\DeleteMessageController::class)
        ->get('/neoncube-private-messages/conversations/{id}', 'neoncube-private-messages.conversations.show', Controllers\ShowConversationController::class),

    (new Extend\ApiSerializer(ForumSerializer::class))
        ->attribute('canMessage', function (ForumSerializer $serializer) {
            return $serializer->getActor()->can('startConversation');
        }),

    (new Extend\ApiSerializer(ForumSerializer::class))
        ->attribute('neoncubePrivateMessagesAllowUsersToReceiveEmailNotifications', function (ForumSerializer $serializer) {
            return $serializer->getActor()->can('neoncube-private-messages.allowUsersToReceiveEmailNotifications');
        }),

    (new Extend\ApiSerializer(CurrentUserSerializer::class))
        ->attribute('unreadMessages', function (CurrentUserSerializer $serializer) {
            return $serializer->getActor()->unread_messages;
        }),

    (new Extend\Settings())
        ->serializeToForum('neoncubePrivateMessagesInitialInstalledVersion', 'neoncube-private-messages.initial_installed_version', function ($value) {
            return $value;
        }),

    (new Extend\Settings())
        ->serializeToForum('neoncubePrivateMessagesReturnKey', 'neoncube-private-messages.return_key', function ($value) {
            return (bool)$value;
        }),

    (new Extend\Settings())
        ->serializeToForum('neoncubePrivateMessagesEnableCustomColors', 'neoncube-private-messages.enable_custom_colors', function ($value) {
            return (bool)$value;
        }),
    (new Extend\Settings())
        ->serializeToForum('neoncubePrivateMessagesRecipientBackgroundColor', 'neoncube-private-messages.recipient_background_color', function ($value) {
            return $value;
        }),
    (new Extend\Settings())
        ->serializeToForum('neoncubePrivateMessagesSenderBackgroundColor', 'neoncube-private-messages.sender_background_color', function ($value) {
            return $value;
        }),
    (new Extend\Settings())
        ->serializeToForum('neoncubePrivateMessagesRecipientTextColor', 'neoncube-private-messages.recipient_text_color', function ($value) {
            return $value;
        }),
    (new Extend\Settings())
        ->serializeToForum('neoncubePrivateMessagesSenderTextColor', 'neoncube-private-messages.sender_text_color', function ($value) {
            return $value;
        }),

    // ✅ 注册通知类型 & 渠道：允许 alert（站内通知）+ email
    (new Extend\Notification())
        ->type(NewPrivateMessageBlueprint::class, MessageSerializer::class, ['alert', 'email']),

    // ✅ 为“有人私信我”设置默认偏好（只对尚未存过偏好的用户生效）
    // notify_* → 站内通知（小铃铛）；email_* → 邮件
    (new Extend\User())
        ->registerPreference('notify_newPrivateMessage', 'boolval', true)   // 默认开启站内通知
        ->registerPreference('email_newPrivateMessage', 'boolval', true),  // 邮件如需默认开，改为 true

    (new Extend\View)
        ->namespace('flarum-private-messages', __DIR__.'/views'),
];
