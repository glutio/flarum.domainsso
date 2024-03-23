import { extend } from 'flarum/extend';
import HeaderSecondary from 'flarum/components/HeaderSecondary';

app.initializers.add('glutio-flarum-domainsso', app => {
  extend(HeaderSecondary.prototype, 'items', function(items) {
    if (items.has('signUp')) {
      items.remove('signUp');
    }
    
    if (items.has('logIn')) {
      const config = JSON.parse(app.forum.attribute("glutio-domainsso"));
      const logInButton = items.get('logIn');
      const host = config.url;
      const login = config.login;
      let redirect = config.redirect;
      redirect = redirect ? '?' + redirect + '=' + encodeURIComponent(window.location) : ''
      const url = host + login + redirect;

      logInButton.attrs.onclick = function() {
        window.location.href = url;
      };
    }
  });
});