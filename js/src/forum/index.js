import { extend } from 'flarum/extend';
import HeaderSecondary from 'flarum/components/HeaderSecondary';
import SettingsPage from 'flarum/components/SettingsPage'
import UserPage from 'flarum/components/UserPage'
import AvatarEditor from 'flarum/components/AvatarEditor'

app.initializers.add('glutio-flarum-domainsso', app => {
  extend(HeaderSecondary.prototype, 'items', function (items) {
    if (items.has('signUp')) {
      items.remove('signUp');
    }

    if (items.has('logIn')) {
      const config = JSON.parse(app.forum.attribute("glutio-domainsso"));
      let redirect = config.redirect;
      redirect = redirect ? '?' + redirect + '=' + encodeURIComponent(window.location) : ''
      const url = config.url + config.login + redirect;

      const logInButton = items.get('logIn');
      logInButton.attrs.onclick = function () {
        window.location.href = url;
      };
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

  extend(AvatarEditor.prototype, 'view', function(html) {
    html.children = [html.children[0]];
  });
});