<?php

namespace Neoncube\FlarumPrivateMessages\Api\Controllers;

use Flarum\Api\Controller\AbstractShowController;
use Flarum\Api\Serializer\BasicUserSerializer;
use Psr\Http\Message\ServerRequestInterface;
use Pusher;
use Tobscure\JsonApi\Document;

class TypingPusherController extends AbstractShowController
{
    /** 保持与原实现一致 */
    public $serializer = BasicUserSerializer::class;

    /**
     * 最小修复要点：
     * 1) 兼容空请求体：避免 null 上取键导致 Warning/Notice（会破坏 headers）
     * 2) 返回与 serializer 匹配的资源（当前 actor），而不是 bool
     * 3) 容器获取增加兜底：优先使用原作者写法 resolve('container')，失败则回退到 Flarum Application
     * 4) 轻量鉴权：仅当 actor.id 与传入 userId 一致且 conversationId 存在时才触发推送
     *    ——正常调用不受影响，避免被恶意伪造为他人频道推送
     */
    public function data(ServerRequestInterface $request, Document $document)
    {
        // ① 统一成数组，防止 null 上取键产生 Notice
        $raw = $request->getParsedBody();
        $data = is_array($raw) ? $raw : [];

        $actor          = $request->getAttribute('actor');      // 当前登录用户（Flarum 提供）
        $userId         = isset($data['userId']) ? (string) $data['userId'] : '';
        $conversationId = $data['conversationId'] ?? null;

        // ② 获取容器：优先保持原作者写法，失败再兜底到 Application
        $container = null;
        if (function_exists('resolve')) {
            try {
                $container = resolve('container');
            } catch (\Throwable $e) {
                // 忽略，继续用兜底方式
            }
        }
        if (!$container && class_exists(\Flarum\Foundation\Application::class)) {
            $container = \Flarum\Foundation\Application::getInstance();
        }

        // ③ 轻量鉴权 + 条件触发：不改变既有交互，仅在参数合理时才推送
        if (
            $actor
            && $userId !== ''
            && (string) $actor->id === $userId
            && $conversationId !== null
            && $container
            && method_exists($container, 'bound')
            && $container->bound(Pusher::class)
        ) {
            // 保持原通道/事件名与负载结构完全一致
            $container->make(Pusher::class)->trigger('private-user' . $userId, 'typing', [
                'conversationId' => $conversationId,
            ]);
        }

        // ④ 返回可被 BasicUserSerializer 序列化的资源（兼容 JSON:API 规范）
        return $actor;
    }
}
