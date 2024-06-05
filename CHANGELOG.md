# FormBuilder HTMX Changelog

## 0.0.4 2024-06-05

- Fix issue where rendering forms/responses in loops over repeater field content failed. Thanks to
  @lemachinarbo for finding and resolving in PR https://github.com/SkyLundy/FormBuilderHtmx/pull/1

## 0.0.3 2024-06-03

- FormBuilderHtmx is now a true drop-in replacement for the FormBuilder `render()` method. All
  FormBuilder methods and properties can be accessed from the `$htmxForms->render()` with the same
  behavior as `$forms->render()`
- Add better per-form tracking, forms and their requests are uniquely identified
- Allows for rendering the same form in multiple locations with submissions/validations unique to
  each where the default FormBuilder embed methods will show errors on all forms if one form has
  errors
- Add request headers to persist form configurations between request/response loop
- Add better checking for HTMX form submissions to differentiate from non-HTMX submissions
- Fix issue with form being replaced with entire page markup

### Known Issues
If the same form is present more than once on the same page and both FormBuilder and FormBuilderHtmx
are used to render them, errors for the non-HTMX form will show up as errors on the HTMX form. This
can be overcome by using FormBuilder HTMX to render all forms.

## 0.0.2 2024-05-05

### Bufix, recommended for all users

- Fixed issue where module was not properly identifying form submissions and may cause
  non-FormBuilder POST requests to fail. Credit to @wbmnfktr for finding and reporting

## 0.0.1 2024-05-01

### Initial release

- Does what README.md says it does
