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

  // 通知卡片组件 —— 兼容旧式对象注册
  app.notificationComponents = app.notificationComponents || {};
  app.notificationComponents.newPrivateMessage = NewPrivateMessageNotification;

  // 路由
  app.routes.conversations = { path: '/conversations', component: ConversationsPage };
  app.routes.messages = { path: '/conversations/:id', component: ConversationsPage };

  // 会话工具入口
  addConversationsDropdown();

  // 注入颜色变量
  setTimeout(() => {
    const colors = getNeoncubePrivateMessagesColors(app);
    const cssStyle = document.documentElement.style;

    cssStyle.setProperty('--neoncube-private-messages-sender-background-color', colors.senderBackgroundColor);
    cssStyle.setProperty('--neoncube-private-messages-recipient-background-color', colors.recipientBackgroundColor);
    cssStyle.setProperty('--neoncube-private-messages-sender-text-color', colors.senderTextColor);
    cssStyle.setProperty('--neoncube-private-messages-recipient-text-color', colors.recipientTextColor);
  });

  // pusher：未读+1（兼容不同模型实现）
  extend(IndexPage.prototype, 'oncreate', function () {
    if (app.pusher) {
      app.pusher.then((object) => {
        const channels = object.channels;
        if (channels.user) {
          channels.user.bind('newMessage', () => {
            const user = app.session.user;
            if (!user) return;
            const cur = (typeof user.unreadMessages === 'function' ? user.unreadMessages() : 0) || 0;

            if (typeof user.pushAttributes === 'function') {
              user.pushAttributes({ unreadMessages: cur + 1 });
            } else if (typeof user.unreadMessages === 'function') {
              user.unreadMessages(cur + 1);
            }
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
   * - 关键：行键必须与 PHP Blueprint::getType() 完全一致：'newPrivateMessage'
   * - 该版本要求使用 `label` 字段显示文字；`name` 会被忽略
   * - 渠道（alert/email）是否可用由后端 Extend\Notification 决定
   */
  const addGridRow = (items) => {
    if (!items.has || !items.has('newPrivateMessage')) {
      items.add('newPrivateMessage', {
        label: app.translator.trans('neoncube-private-messages.forum.notifications.new_private_message'),
        icon: 'fas fa-comment-alt',
      });
    }
  };

  // 兼容不同版本：同时挂到 class 和 prototype
  extend(NotificationGrid, 'notificationTypes', addGridRow);
  extend(NotificationGrid.prototype, 'notificationTypes', addGridRow);
});
