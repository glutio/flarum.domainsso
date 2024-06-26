import { extend } from 'flarum/extend';
import HeaderSecondary from 'flarum/components/HeaderSecondary';
import SettingsPage from 'flarum/components/SettingsPage'
import UserPage from 'flarum/components/UserPage'
import AvatarEditor from 'flarum/components/AvatarEditor'
import LogInModal from 'flarum/components/LogInModal'

const extensionName = 'glutio-domainsso'
let clicks = 0;
let timeout = undefined;

app.initializers.add(extensionName, app => {
  extend(HeaderSecondary.prototype, 'items', function (items) {
    if (items.has('signUp')) {
      items.remove('signUp');
    }

    if (items.has('logIn')) {
      const url = app.forum.attribute(extensionName + '.url') ?? '';
      const login = app.forum.attribute(extensionName + '.login') ?? '';
      let redirect = app.forum.attribute(extensionName + '.redirect') ?? '';
      redirect = redirect ? `?${redirect}=${encodeURIComponent(window.location)}` : '';
      
      const href = url + login + redirect;
      if (href) {        
        const logInButton = items.get('logIn');
        const oldClick = logInButton.attrs.onclick;
        logInButton.attrs.onclick = () => {          
          clicks++;
          if (clicks == 1) {
            timeout = setTimeout(() => {
              if (clicks == 1) {
                clicks = 0;
                window.location.href = href;
              }
            }, 300);
          } else if (clicks > 1) {
            clearTimeout(timeout);
            clicks = 0;
            oldClick.call(logInButton);
          }          
        };
      }
    }
    extend(LogInModal.prototype, "title", function (title) {
      title[0] = "Admin " + app.translator.trans('core.forum.log_in.title');
    });
    extend(LogInModal.prototype, "content", function (content) {
      if (content.length > 1) {
        content.pop();
      }
    });

    extend(LogInModal.prototype, "fields", function (items) {
      if (items.has('remember')) {
        items.remove('remember');
      }
    });

    extend(SettingsPage.prototype, 'settingsItems', function (items) {
      if (items.has('account')) {
        items.remove('account');
      }
    });

    extend(UserPage.prototype, 'navItems', function (items) {
      if (items.has('security')) {
        items.remove('security');
      }
    });

    extend(AvatarEditor.prototype, 'view', function (html) {
      html.children = [html.children[0]];
    });
  })
});