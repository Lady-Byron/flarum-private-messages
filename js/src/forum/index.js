import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';

import IndexPage from 'flarum/forum/components/IndexPage';
import NotificationGrid from 'flarum/forum/components/NotificationGrid';

import Message from './models/Message';
import Conversation from './models/Conversation';
import ConversationUser from './models/ConversationUser';

import User from 'flarum/common/models/User';
import Model from 'flarum/common/Model';

import ConversationsPage from './components/ConversationsPage';
import NewPrivateMessageNotification from './components/NewPrivateMessageNotification';

import addConversationsDropdown from './addConversationsDropdown';
import { getNeoncubePrivateMessagesColors } from '../admin-forum-common';

app.initializers.add('neoncube-private-messages', () => {
  // 模型注册
  app.store.models.messages = Message;
  app.store.models.conversations = Conversation;
  app.store.models.conversation_users = ConversationUser;

  User.prototype.conversations = Model.hasMany('conversations');
  User.prototype.unreadMessages = Model.attribute('unreadMessages');

  // 通知卡片组件（官方推荐写法）
  app.notificationComponents.add('newPrivateMessage', NewPrivateMessageNotification);

  // 路由
  app.routes.conversations = { path: '/conversations', component: ConversationsPage };
  app.routes.messages = { path: '/conversations/:id', component: ConversationsPage };

  // 颜色变量
  setTimeout(() => {
    const colors = getNeoncubePrivateMessagesColors(app);
    const cssStyle = document.documentElement.style;

    cssStyle.setProperty('--neoncube-private-messages-sender-background-color', colors.senderBackgroundColor);
    cssStyle.setProperty('--neoncube-private-messages-recipient-background-color', colors.recipientBackgroundColor);
    cssStyle.setProperty('--neoncube-private-messages-sender-text-color', colors.senderTextColor);
    cssStyle.setProperty('--neoncube-private-messages-recipient-text-color', colors.recipientTextColor);
  });

  // pusher：更新未读数（用 pushAttributes，避免把 accessor 变成 Stream）
  extend(IndexPage.prototype, 'oncreate', function () {
    if (app.pusher) {
      app.pusher.then((object) => {
        const channels = object.channels;
        if (channels.user) {
          channels.user.bind('newMessage', () => {
            const cur = app.session.user?.unreadMessages?.() ?? 0;
            app.session.user?.pushAttributes({ unreadMessages: cur + 1 });
            m.redraw();
          });
        }
      });
    }
  });

  extend(IndexPage.prototype, 'onremove', function () {
    if (app.pusher) {
      app.pusher.then((object) => {
        const channels = object.channels;
        if (channels.user) {
          channels.user.unbind('newMessage');
        }
      });
    }
  });

  /**
   * 通知设置行（NotificationGrid）
   * 关键点：
   *  - 扩展到类本身：NotificationGrid（不是 prototype）
   *  - 行键名必须与 Blueprint::getType() 完全一致：'newPrivateMessage'
   *  - 只提供 name 与 icon；name 用翻译文本
   *  - 是否可用的渠道（alert/email）由后端 Extend\Notification 决定
   */
  extend(NotificationGrid, 'notificationTypes', function (items) {
    items.add('newPrivateMessage', {
      name: app.translator.trans('neoncube-private-messages.forum.notifications.new_private_message'),
      icon: 'fas fa-comment-alt',
    });
  });

  // 工具栏入口
  addConversationsDropdown();
});
