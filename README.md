# FormBuilder HTMX

A zero-configuration drop in module to power your ProcessWire Pro FormBuilder forms with AJAX provided by HTMX.

Features:

- Converts any form build using the Pro FormBuilder module to AJAX
- Forms are processed in place, no page refreshes after submission
- Is compatible with ProCache
- Does not conflict with existing styles and JavaScript
- All validation, errors, and messages provided by FormBuilder work as expected
- Is compatible with [FieldtypeFormSelect](https://github.com/SkyLundy/FieldtypeFormSelect)
- Can be used on a per-form basis alongside FormBuilder's methods of handling forms

## Requirements

- ProcessWire >= 3.0
- FormBuilder
- PHP >= 8.1
- The HTMX library present and loaded (is separate, not provided with this module)

This module was developed using FormBuilder 0.5.5, however it should be compatible with other versions.

## How To Use
Ensure that HTMX is present and loaded with your page assets. [HTMX instructions here](https://htmx.org/docs/#installing)

This is only compatible when embedding forms using "Option C: Preferred Method". Refer to the 'Embed' tab of your Form Setup page for additional details.

Where you want a form rendered HTMX ready, replace the `$forms->render('your_form_name')` method call with `$htmxForms->render('your_form_name')`. All other FormBuilder markup, theming, JavaScript, etc. remains untouched.

The `$htmxForms->render()` method is a drop-in replacement for the FormBuilder render method. It can be hooked and accepts the second `$vars` array argument passed on to FormBuilder. Refer to FormBuilder documentation for more information.

**NOTE**: CSRF protection must be disabled for forms using HTMX/AJAX. ProcessWire does not recognize the form submission AJAX call as the same as that of the user so CSRF errors will occur.

Please keep your use case and type of data a form processes in mind when choosing which forms to enable AJAX submissions. For example, login forms may not be a good candidate for AJAX handling, however contact and less critical forms should be fine.

## How Does It Work?

When rendering forms to the page, the form markup is modified before output with the form attribute `method="post"` replaced with `hx-post`. This transfers submission handling to HTMX and AJAX.

When a form is submitted, FormBuilder handles processing the data as usual and then returns the full page markup which would otherwise trigger a page refresh. Instead, FormBuilderHtmx parses the page markup before rendering, the form extracted from the page, `method="post"` is replaced with `hx-post`, and then the form markup is returned to complete the AJAX request where HTMX then replaces the form in place with the results of the submission.

## Hooking

FormBuilderHtmx provides a hookable method to work with the markup being output to the page after a form has been processed by FormBuilder.

```php
$wire->addHookAfter('FormBuilderHtmx::render', function(HookEvent $event) {
  $formHtmlMarkup = $event->return;

  // Modify markup as desired
  $outputMarkup =<<<EOT
  <div class="%{CLASS}">
    <p>Look at that beautiful AJAX processed form:</p>
    {$formHtmlMarkup}
  </div>
  EOT;

  $event->return = $outputMarkup;
});
```