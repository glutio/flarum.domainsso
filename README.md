Delegate Flarum login, logout and session validation to an SSO endpoint on the same domain by forwarding the domain-scoped cookies. 

Example:
1. SSO url is `https://example.com` and has login endpoint `/api/auth/signin`, logout endpoint `/api/auth/signout` and session endpoint `/api/auth/session`.
2. Flarum url is `https://flarum.example.com` with DomainSSO extension enabled.
3. The user clicks  `Log In` on the Flarum site and is redirected to `https://example.com/api/auth/signin` where they log in and a domain-scoped token cookie is generated.
4. The user is redirected back to Flarum at `https://flarum.example.com` and the domain-scoped cookie is forwarded to `https://example.com/api/auth/session`.
5. If based on the domain-scoped cookie the session is validated (returning session JSON) Flarum logs in the user based on the user's email address (the user is created in Flarum's database on first login).
6. The user clicks `Log Out` on the Flarum site and is logged out of Flarum and redirected to `https://example.com/api/auth/signout` where the domain-scoped session is terminated.

#### Double-clicking `Log In` on the Flarum site pops up a login dialog for local Flarum admin to login to setup or fix the extension's settings.

Initially the the extension is implemented to work with NextAuth.js and expects the session JSON to have a user property:
```
{
    "user": {
        "name": "John Doe",
        "email": "john.doe@example.com",
        "image": "https://example.com/image.jpg"
    }
}
```
