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

app.initializers.add('neoncube-private-messages', (app) => {
  // 模型绑定
  app.store.models.messages = Message;
  app.store.models.conversations = Conversation;
  app.store.models.conversation_users = ConversationUser;

  User.prototype.conversations = Model.hasMany('conversations');
  User.prototype.unreadMessages = Model.attribute('unreadMessages');

  // 通知卡片（沿用原来的旧式对象注册）
  app.notificationComponents.newPrivateMessage = NewPrivateMessageNotification;

  // 路由
  app.routes.conversations = { path: '/conversations', component: ConversationsPage };
  app.routes.messages = { path: '/conversations/:id', component: ConversationsPage };

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

  // Pusher 未读数 +1（更稳的写法）
  extend(IndexPage.prototype, 'oncreate', () => {
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

  extend(IndexPage.prototype, 'onremove', () => {
    if (app.pusher) {
      app.pusher.then((object) => {
        const channels = object.channels;
        if (channels.user) {
          channels.user.unbind('newMessage');
        }
      });
    }
  });

  // NotificationGrid：关键修复 —— 显式声明可用渠道
  extend(NotificationGrid.prototype, 'notificationTypes', (items) => {
    // 不再前端 gate；是否允许发邮件由后端权限控制，Grid 只负责渲染行
    items.add('newPrivateMessage', {
      label: app.translator.trans('neoncube-private-messages.forum.notifications.new_private_message'),
      icon: 'fas fa-comment-alt',
      methods: ['alert', 'email'], // ★ 告诉 Grid 这条类型支持这两列 → 生成真正的输入框
    });
  });
});
