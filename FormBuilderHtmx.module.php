<?php namespace ProcessWire;

class FormBuilderHtmx extends Process implements Module {

  public static function getModuleInfo() {
    return [
      'title' => 'FormBuilder HTMX',
      'summary' => __('Render HTMX ready FormBuilder forms submitted via AJAX', __FILE__),
      'version' => '001',
      'href' => 'https://github.com/SkyLundy',
      'icon' => 'code',
      'autoload' => true,
      'singular' => true,
      'requires' => [
        'FormBuilder',
        'ProcessWire>=300',
        'PHP>=8.1'
      ]
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function init() {
    $this->wire->set('htmxForms', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function ready() {
    $this->addHooks();
  }

  /**
   * Provides a drop-in $htmxForms->render() replacement method for $forms->render() where an HTMX
   * powered form is desired
   * @param  string     $formName           Name of form to render
   * @param  array|Page $vars               Value passed to FormBuilder::render()
   * @param  string     $indicatorSelector  CSS selector for the target activity element
   */
  public function ___render(
    string $formName,
    array|Page $vars = [],
    ?string $indicatorSelector = null
  ): string {
    return $this->renderPreparedMarkup(
      wire('forms')->render($formName, $vars),
      $indicatorSelector
    );
  }

  /**
   * Add hooks to render return markup on form submission
   */
  private function addHooks(): void {
    $this->wire->addHookAfter('Page::render', null, function(HookEvent $e) {

      if (!$this->isFormBuilderSubmissionRequest()) {
        return;
      }

      $input = wire('input');
      $submitKey = $input->_submitKey;
      $inputfieldForm = $input->_InputfieldForm;

      $formMarkup = $this->renderAjaxResponseMarkup($inputfieldForm, $submitKey, $e->return);

      $formMarkup && $e->return = $this->renderPreparedMarkup($formMarkup);
    });
  }

  /**
   * Looks for FormBuilder request body signatures to determine if the current request is a
   * FormBuilder POST request to process data
   */
  private function isFormBuilderSubmissionRequest(): bool {
      $input = wire('input');
      $isPostRequest = $input->requestMethod('post');
      $submitKey = $input->_submitKey;
      $inputfieldForm = $input->_InputfieldForm;

      if ($isPostRequest && $submitKey && $inputfieldForm) {
        return true;
      };

      return false;
  }

  /**
   * Handles modifying the form markup when initially rendered to the page
   * @param  string $renderedForm HTMX ready form markup
   */
  private function renderPreparedMarkup(
    string $renderedForm,
    ?string $indicatorSelector = null
  ): string {
    $indicator = $indicatorSelector ? "hx-indicator='{$indicatorSelector}'" : '';

    // Add insert HTMX attributes on the form element, indicator if provided
    $markup = preg_replace(
      '/(method="post")/i',
      "hx-post hx-disabled-elt='button[type=submit]' {$indicator}",
      $renderedForm
    );

    return $markup;
  }

  /**
   * Handles modifying the form markup returned after a form is processed
   * Finds a FormBuilder form in a provided markup string and returns the extracted FormBuilder form
   * @param  string $formName       Name of form from requests parameters
   * @param  string $renderedMarkup Rendered markup that may contain a FormBuilder form
   */
  public function renderAjaxResponseMarkup(
    string $formName,
    string $submitKey,
    string $renderedMarkup
  ): ?string {
    $patternFormName = preg_quote($formName);

    // Find the full FormBuilder form markup block
    $formBuilderMarkup = preg_match(
      "/<div class=[\"']FormBuilder FormBuilder-{$patternFormName}((.|\n|\r|\t)*)<!--\/.FormBuilder-->/U",
      $renderedMarkup,
      $matches
    );

    if (!$formBuilderMarkup) {
      return null;
    }

    // Check for the individual submit key to ensure that we have the correct form out of similar
    // forms that may be present. Get longest string containing the key to ensure we aren't
    // selecting a match containing a fragment of form markup
    $targetMarkup = array_reduce($matches, function($match, $markup) use ($submitKey) {
      $containsSubmitKey = str_contains($markup, $submitKey);

      if ($containsSubmitKey) {
        return $match = strlen($markup) > strlen($match ?? '') ? $markup : $match;
      }

      return $match;
    }, null);

    if (!$targetMarkup) {
      return null;
    }

    // Extract the contents of the form element
    // HTMX will replace the child elements of the existing form element with the returned markup
    preg_match('/(<form .*?>)(.|\n)*?(<\/form>)/', $targetMarkup, $matches);

    [$formMarkup, $formOpenTag] = $matches;

    $elements = str_replace($formOpenTag, '', $formMarkup);
    $elements = str_replace('</form>', '', $elements);

    return $elements;
  }
}