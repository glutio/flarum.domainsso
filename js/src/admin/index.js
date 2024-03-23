import app from 'flarum/admin/app';

const settingsPrefix = 'glutio-domainsso'
app.initializers.add(settingsPrefix, () => {
    app.extensionData
        .for(settingsPrefix)
        .registerSetting(
            {
                setting: settingsPrefix + '.url',
                type: 'text',
                label: app.translator.trans('glutio-domainsso.admin.settings.url_label'),
            })
        .registerSetting(
            {
                setting: settingsPrefix + '.login',
                type: 'text',
                label: app.translator.trans('glutio-domainsso.admin.settings.login_label'),
            }
        )
        .registerSetting(
            {
                setting: settingsPrefix + '.logout',
                type: 'text',
                label: app.translator.trans('glutio-domainsso.admin.settings.logout_label'),
            }
        )
        .registerSetting(
            {
                setting: settingsPrefix + '.session',
                type: 'text',
                label: app.translator.trans('glutio-domainsso.admin.settings.session_label'),
            }
        )
        .registerSetting(
            {
                setting: settingsPrefix + '.redirect',
                type: 'text',
                label: app.translator.trans('glutio-domainsso.admin.settings.redirect_label'),
            }
        );
});