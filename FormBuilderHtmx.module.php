<?php namespace ProcessWire;

class FormBuilderHtmx extends Wire implements Module {

  /**
   * Names of request headers to perist data during per-form request/response
   */
  private const ID_REQUEST_HEADER = 'Fb-Htmx-Id';
  private const INDICATOR_REQUEST_HEADER = 'Fb-Htmx-Indicator';

  public static function getModuleInfo()
  {
    return [
      'title' => 'FormBuilder HTMX',
      'summary' => __('Render HTMX ready FormBuilder forms submitted via AJAX', __FILE__),
      'version' => '003',
      'href' => 'https://processwire.com/talk/topic/29964-formbuilderhtmx-a-zero-configuration-pro-formbuilder-companion-module-to-enable-ajax-form-submissions/',
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
  public function init()
  {
    $this->wire->set('htmxForms', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function ready()
  {
    $this->addPostFormProcessingHook();
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
    // Hooks only this method call on initial render to only target HTMX forms
    $this->wire->addHookAfter('FormBuilderProcessor::render', function($e) use ($indicator) {
      $e->return = $this->renderHtmxFormMarkup($e->return, $indicator);

      $e->removeHook(null);
    });

    return wire('forms')->render($formName, $vars);
  }

  /**
   * Add hooks to handle FormBuilder HTMX submissions after page render
   */
  private function addPostFormProcessingHook(): void
  {
    $this->wire->addHookAfter(
      'Page::render',
      fn ($e) =>  $this->isHtmxRequest() && ( 
        !empty($e->return) && $e->return = $this->renderHtmxResponse($e->return)
      )
    );
  }

  /**
   * Looks for FormBuilder request signatures to determine if the current request is both a
   * FormBuilder submission and an HTMX request
   */
  private function isHtmxRequest(): bool
  {
      $input = wire('input');

      $requestHeaders = getallheaders() + ['Hx-Request' => false, self::ID_REQUEST_HEADER => null];

      return $input->requestMethod('post') &&
             $input->_submitKey &&
             $input->_InputfieldForm &&
             !!$requestHeaders[self::ID_REQUEST_HEADER] &&
             $requestHeaders['Hx-Request'] === 'true';
  }

  /**
   * Handles modifying the form markup when initially rendered to the page
   * - Creates a unique ID to identify each form individually
   * - Adds wrapper with unique ID for HTMX response swap
   * - Adds HTMX attributes to form
   * - Adds request headers to persist data between rendering/processing/response loop
   *
   * @param string $renderedForm FormBuilder form markup
   * @param string $indicator    HTML "loading" indicator element, falls back to pulling from
   *                             request headers if not present
   */
  private function renderHtmxFormMarkup(string $renderedForm, ?string $indicator = null): string
  {
    $indicator = $this->indicatorSelector($indicator);
    $id = $this->htmxFormId();
    $renderedForm = $this->addFormBuilderHtmxContainer($renderedForm, $id);

    // Headers are used to persist data between page render->submission->HTMX response
    $headers = json_encode([
      self::ID_REQUEST_HEADER => $id,
      self::INDICATOR_REQUEST_HEADER => $indicator
    ]);

    $htmxAttributes = array_filter([
      'hx-post',
      "hx-headers='{$headers}'",
      "hx-disabled-elt='button[type=submit]'",
      "hx-target='#{$id}'",
      "hx-swap='outerHTML'",
      $indicator ? "hx-indicator='{$indicator}'" : null,
    ]);

    return preg_replace('/(method="post")/i', implode(' ', $htmxAttributes), $renderedForm);
  }

  /**
   * Inserts
   * @param string      $renderedForm Rendered form markup
   * @param string|null $id           Optional ID, otherwise will be pulled from headers, or generated
   */
  private function addFormBuilderHtmxContainer(string $renderedForm, ?string $id = null): string
  {
    $id ??= $this->htmxFormId();

    $markup = "<div id='{$id}' data-formbuilder-htmx>{$renderedForm}</div>";

    // Markup regions removes comments before inserting content so this tag must be added to
    // indicate the end of the markup for a target form in place of the end FormBuilder comment
    $this->config->useMarkupRegions && $markup .= '<span data-formbuilder-htmx-end></span>';

    return $markup;
  }

  /**
   * Gets an existing ID from request headers, or creates a new one for rendering
   */
  private function htmxFormId(): string
  {
    return getallheaders()[self::ID_REQUEST_HEADER] ?? 'fb-htmx-' . (new WireRandom)->alphanumeric(10);
  }

  /**
   * Gets a selector for an indicator element from headers if it exists, or returns fallback param
   * @param  string|null $indicator Optional fallback indicator
   */
  private function indicatorSelector(?string $indicator = null): ?string
  {
    return getallheaders()[self::INDICATOR_REQUEST_HEADER] ?? $indicator;
  }

  /**
   * Finds the form that has been submitted and extracts the markup to return what HTMX expects
   * @param  string $renderedPageMarkup Rendered full page markup
   */
  private function renderHtmxResponse(string $renderedPageMarkup): string
  {
    $pattern = "/\n?<div id=[\"']{$this->htmxFormId()}[\"']((.|\n|\r|\t)*)(<!--\/.FormBuilder-->|<span data-formbuilder-htmx-end><\/span>)/U";

    preg_match($pattern, $renderedPageMarkup, $matches);

    return $matches[0] ?? '';
  }
}
