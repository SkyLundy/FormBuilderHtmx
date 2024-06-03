<?php namespace ProcessWire;

class FormBuilderHtmx extends Wire implements Module {

  private const HTMX_FORM_ID_HEADER = 'Fb-Htmx-Id';

  public static function getModuleInfo() {
    return [
      'title' => 'FormBuilder HTMX',
      'summary' => __('Render HTMX ready FormBuilder forms submitted via AJAX', __FILE__),
      'version' => '002',
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
   * @param  string            $formName  Name of form to render
   * @param  FormBuilderRender $indicator CSS selector for the target activity element
   * @param  array|Page        $vars      Value passed to FormBuilder::render()
   */
  public function ___render(
    string $formName,
    array|Page $vars = [],
    ?string $indicator = null
  ): FormBuilderRender {
    // Set up hook only for this method call
    $hookId = $this->wire->addHookAfter(
      'FormBuilderProcessor::render',
      fn ($e) => $e->return = $this->renderHtmxMarkup($e->return, $indicator)
    );

    $formBuilderRender = wire('forms')->render($formName, $vars);

    // Hook only for this render in this method to add HTMX
    $this->wire->removeHook($hookId);

    return $formBuilderRender;
  }

  /**
   * Add hooks to render return markup on form submission
   */
  private function addHooks(): void {
    $this->wire->addHookAfter('Page::render', function(HookEvent $e) {

      if (!$this->isHtmxFormBuilderRequest()) {
        return;
      }

      $formMarkup = $this->renderAjaxResponseMarkup(
        wire('input')->_InputfieldForm,
        $e->return
      );

      $formMarkup && $e->return = $this->renderHtmxMarkup($formMarkup);
    });
  }

  /**
   * Looks for FormBuilder request body/header signatures to determine if the current request is a
   * FormBuilder HTMX POST request
   */
  private function isHtmxFormBuilderRequest(): bool {
      $input = wire('input');
      $isPostRequest = $input->requestMethod('post');
      $submitKey = $input->_submitKey;
      $inputfieldForm = $input->_InputfieldForm;

      $requestHeaders = getallheaders();

      $isHtmxRequest = $requestHeaders['Hx-Request'] ?? 'false';
      $formBuilderHtmxId = $requestHeaders[self::HTMX_FORM_ID_HEADER] ?? null;

      return $isPostRequest &&
             $submitKey &&
             $inputfieldForm &&
             $formBuilderHtmxId &&
             $isHtmxRequest === 'true';
  }

  /**
   * Handles modifying the form markup when initially rendered to the page
   * @param  string $renderedForm HTMX ready form markup
   */
  private function renderHtmxMarkup(
    string $renderedForm,
    ?string $indicator = null
  ): string {
    $indicator = $indicator ? "hx-indicator='{$indicator}'" : null;

    // Add an ID to place into the headers to identify this specific form
    // Solves issue where the same form rendered 2x causes issues finding this form vs other on page
    $headers = json_encode([self::HTMX_FORM_ID_HEADER => $this->htmxFormId()]);

    $htmxAttributes = array_filter([
      'hx-post',
      "hx-headers='{$headers}'",
      "hx-disabled-elt='button[type=submit]'",
      $indicator ? "hx-indicator='{$indicator}'" : null,
    ]);

    // Add insert HTMX attributes on the form element, indicator if provided
    return preg_replace('/(method="post")/i', implode(' ', $htmxAttributes), $renderedForm);
  }

  /**
   * Gets the current HTMX form ID or creates a new one
   */
  private function htmxFormId(): string {
    return getallheaders()[self::HTMX_FORM_ID_HEADER] ?? (new WireRandom)->alphanumeric(5);
  }

  /**
   * Handles modifying the form markup returned after a form is processed
   * First checks for submission results markup, then searches for the corresponding form for return
   *
   * @param  string $formName          Name of form from requests parameters
   * @param  string $formBuilderMarkup Rendered markup that may contain a FormBuilder form
   */
  private function renderAjaxResponseMarkup(string $formName, string $formBuilderMarkup): ?string {
    // $resultMarkup = $this->getSubmissionResultMarkup($formName, $formBuilderMarkup);

    // if ($resultMarkup) {
    //   return $resultMarkup;
    // }

    return $this->getSubmissionFormMarkup($formName, $formBuilderMarkup);
  }

  /**
   * Searches for form submission response markup, returns as string if present
   *
   * @param  string $formName          Name of form from requests parameters
   * @param  string $formBuilderMarkup FormBuilder markup that may contain a submission result
   */
  private function getSubmissionResultMarkup(string $formName, string $formBuilderMarkup): ?string {
    $formName = preg_quote($formName);

    $resultMarkup = preg_match(
      "/<div id=[\"']FormBuilderSubmitted[\"'] data-name=[\"']{$formName}[\"']>((.|\n|\r|\t)*)<!--\/.FormBuilder-->/U",
      $formBuilderMarkup,
      $matches
    );

    if (!$resultMarkup) {
      return null;
    }

    return $matches[0];
  }

  /**
   * Searches for FormBuilder form markup, returns as string if present
   *
   * @param  string $formName          Name of form from requests parameters
   * @param  string $formBuilderMarkup FormBuilder markup that may contain a submission result
   */
  private function getSubmissionFormMarkup(string $formName, string $formBuilderMarkup): ?string {
     $patternFormName = preg_quote($formName);

    // Find the full FormBuilder form markup block
    $formBuilderMarkup = preg_match_all(
      "/<div class=[\"']FormBuilder FormBuilder-{$patternFormName}((.|\n|\r|\t)*)<!--\/.FormBuilder-->/U",
      // "/<div class=[\"']FormBuilder FormBuilder-{$patternFormName}((.|\n|\r|\t)*)<!--\/.FormBuilder-->/U",
      $formBuilderMarkup,
      $matches
    );
dump($this->htmxFormId());
dump($matches);
die;
    return $matches[0];

    if (!$formBuilderMarkup) {
      return null;
    }

    // $fbElements = array_column($matches, 0);
    [self::HTMX_FORM_ID_HEADER => $fbHtmxId] = getallheaders();
dump($fbHtmxId, $formName);
    foreach ($matches as $match) {
      dump($match);
    }
die;
    $match = array_reduce(
      $matches[0],
      fn ($match, $fbElement) => $match = str_contains($fbElement, $fbHtmxId) ? $fbElement : $match,
      null
    );

    dd($match);
    die;

    $markup = $matches[0];

    // Extract the contents of the form element
    // HTMX will replace the child elements of the existing form element with the returned markup
    /* preg_match('/(<form .*?>)(.|\n)*?(<\/form>)/', $matches[0], $matches); */
bd($matches);
bd('fired');
    [$formMarkup, $formOpenTag] = $matches;

    $elements = str_replace($formOpenTag, '', $formMarkup);
    $elements = str_replace('</form>', '', $elements);

    return $elements;
  }
}