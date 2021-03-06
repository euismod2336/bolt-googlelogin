Bolt Google Login
======================

## Installation
1. Upload the extension to *[bolt install dir]*/extensions/local/euismod2336/googlelogin/
2. Set up credentials at:
https://console.developers.google.com/apis/credentials

    Choose an 'OAuth Client ID', then 'Web Application'. Fill the applicable data and set as redirect uri *YOUR DOMAIN* + '/bolt/extensions/oauth2callback'. Press 'Create', download the JSON file and set this in the configuration of the extension in the backend of Bolt.

## Usage in template
If you want to use twig functions in a content type's custom field, you need to enable this manually per field per content type. You can do this by adding `allowtwig: true` to the field of the content type, e.g.:
```yaml
pages:
    name: Pages
    singular_name: Page
    fields:
      title:
        type: text
        ...
      body:
        type: html
        height: 300px
        allowtwig: true
       ...
```

After you've done that, you can use the functions `{{ googlelogin_name() }}`,`{{ googlelogin_email() }}`,`{{ googlelogin_isLoggedIn() }}`, `{{ googlelogin_getLoginUrl() }}` in your pages.

You can easily protect a page by putting `{{ requiregooglelogin() }}` at the top of that page. You can secure the contents by putting it inside:
```twig
{{ requiregooglelogin() }}

{% if googlelogin_isLoggedIn() %}
<p>... your contents ...</p>
{% endif %}
```
This will automatically redirect the user (if not logged in) to the `login_page` in the extension's configuration file and redirect it back to the restricted page after logging in. That page should contain the code `{{ googleloginpage() }}`

Only users listed in `allowed_emails` in the extension's configuration file will be able to log in.

