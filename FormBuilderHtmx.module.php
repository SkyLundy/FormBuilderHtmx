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
   * @param  string $formName Name of form to render
   */
  public function render(string $formName): string {
    return $this->replaceFormPostMethod(
      wire('forms')->render($formName)
    );
  }

  /**
   * Add hooks to operations where necessary
   */
  private function addHooks(): void {
    $this->wire->addHookAfter('Page::render', null, function(HookEvent $e) {

      if (!$this->isFormBuilderSubmissionRequest()) {
        return;
      }

      $input = wire('input');
      $submitKey = $input->_submitKey;
      $inputfieldForm = $input->_InputfieldForm;

      $formMarkup = $this->renderForm($inputfieldForm, $submitKey, $e->return);

      if ($formMarkup) {
        $e->return = $this->replaceFormPostMethod($formMarkup);
      }
    });
  }

  /**
   * Looks for common FormBuilder request body signatures to determine if this is a FormBuilder
   * request to process data
   */
  private function isFormBuilderSubmissionRequest(): bool {
      $input = wire('input');
      $submitKey = $input->_submitKey;
      $inputfieldForm = $input->_InputfieldForm;

      if (!$input->requestMethod('post') || !$submitKey || !$inputfieldForm) {
        return false;
      };

      return true;
  }

  /**
   * Swaps the form attribute `method="post"' with the HTMX 'hx-post' attribute
   * @param  string $renderedForm Full rendered form
   */
  private function replaceFormPostMethod(string $renderedForm): string {
    return preg_replace('/(method="post")/i', 'hx-post', $renderedForm);
  }

  /**
   * Finds a FormBuilder form in a provided markup string and returns the extracted FormBuilder form
   * @param  string $formName       Name of form from requests parameters
   * @param  string $renderedMarkup Rendered markup that may contain a FormBuilder form
   */
  public function ___renderForm(
    string $formName,
    string $submitKey,
    string $renderedMarkup
  ): ?string {
    $patternFormName = preg_quote($formName);

    $hasForm = preg_match(
      "/<div class=[\"']FormBuilder FormBuilder-{$patternFormName}((.|\n|\r|\t)*)<!--\/.FormBuilder-->/U",
      $renderedMarkup,
      $matches
    );

    if (!$hasForm) {
      return null;
    }

    // Check for the individual submit key to ensure that we have the correct form out of similar
    // forms that may be present. Get longest string length to make sure we aren't selecting a
    // match containing a fragment of the form markup
    $form = array_reduce($matches, function($match, $markup) use ($submitKey) {
      $containsSubmitKey = str_contains($markup, $submitKey);

      if ($containsSubmitKey) {
        return $match = strlen($markup) > strlen($match) ? $markup : $match;
      }

      return $match;
    }, null);


    return $form ? preg_replace('/(method="post")/i', 'hx-post', $matches[0]) : null;
  }
}