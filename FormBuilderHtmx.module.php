<?php

namespace ProcessWire;

class FormBuilderHtmx extends Wire implements Module
{

    /**
     * Names of request headers to perist data during per-form request/response
     */
    private const ID_REQUEST_HEADER = 'fb-htmx-id';
    private const INDICATOR_REQUEST_HEADER = 'fb-htmx-indicator';

    /**
     * Memoized parsed request headers
     * @var array
     */
    private array $requestHeaders = [];

    /**
     * Holds the markup for a submitted form if it exists
     * @var string
     */
    private string $processedForm = '';

    public static function getModuleInfo()
    {
        return [
            'title' => 'FormBuilder HTMX',
            'summary' => __('Render HTMX ready FormBuilder forms submitted via AJAX', __FILE__),
            'version' => '101',
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
     * @param  string       $formName       Name of form to render
     * @param  array|Page   $vars           Value passed to FormBuilder::render()
     * @param  string|null  $indicator      Optional CSS selector for the activity indicator shown
     *                                      when the form is submitted
     * @param  array        $formAttributes Array of strings added to the <form> element on render
     */
    public function ___render(
        string $formName,
        array|Page $vars = [],
        ?string $indicator = null,
        array $formAttributes = []
    ): FormBuilderRender {
        $this->wire->addHookAfter('FormBuilderProcessor::render', function($e) use ($indicator, $formAttributes) {
            // Check if this is a form submitted using FormBuilderHtmx
            // If so, tore markup and return just the form instead of the entire page on page render
            if (!$this->processedForm && $this->isHtmxSubmittedForm($e->return)) {
                $this->processedForm = $this->renderHtmxFormMarkup(
                    $e->return,
                    $indicator,
                    $formAttributes
                );
            }

            $e->return = $this->renderHtmxFormMarkup($e->return, $indicator, $formAttributes);

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
            fn (HookEvent $e) => $this->isHtmxRequest() && $e->return = $this->processedForm
        );
    }

    /**
     * Looks for FormBuilder request signatures to determine if the current request is both a
     * FormBuilder submission and an HTMX request
     */
    private function isHtmxRequest(): bool
    {
        $input = wire('input');

        return $input->requestMethod('post') &&
               $input->_submitKey &&
               $input->_InputfieldForm &&
               $this->getHeaderValue(self::ID_REQUEST_HEADER, false) &&
               $this->getHeaderValue('hx-request', false) === 'true';
    }

    /**
     * Determines if the rendered form markup was the form that was submitted
     * @param  string  $formMarkup Markup from FormBuilderProcessor::render hook event
     */
    private function isHtmxSubmittedForm(string $formMarkup): bool
    {
        if (!$this->isHtmxRequest()) {
            return false;
        }

        $submittedFormName = wire('input')->_InputfieldForm;

        return preg_match(
            "/<div id=[\"']FormBuilderSubmitted[\"']\sdata-name=[\"']{$submittedFormName}[\"']>/",
            $formMarkup
        );

        return $match;
    }

    /**
     * Handles modifying the form markup when initially rendered to the page
     * - Creates a unique ID to identify each form individually
     * - Adds wrapper with unique ID for HTMX response swap
     * - Adds HTMX attributes to form
     * - Adds request headers to persist data between rendering/processing/response loop
     *
     * @param string $renderedForm FormBuilder form markup
     * @param string $indicator      HTML "loading" indicator element, falls back to pulling from
     *                               request headers if not present
     * @param array  $formAttributes Additional form attributes to be added to the <form> element
     */
    private function renderHtmxFormMarkup(
        string $renderedForm,
        ?string $indicator = null,
        array $formAttributes = []
    ): string {
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
            "hx-swap='innerHTML'",
            $indicator ? "hx-indicator='{$indicator}'" : null,
            ...$formAttributes
        ]);

        $htmxAttributes = array_unique($htmxAttributes);

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

        return "<div id='{$id}' data-formbuilder-htmx>{$renderedForm}</div>";
    }

    /**
     * Gets an existing ID from request headers, or creates a new one for rendering
     */
    private function htmxFormId(): string
    {
        return $this->getHeaderValue(
            self::ID_REQUEST_HEADER,
            'fb-htmx-' . (new WireRandom)->alphanumeric(10)
        );
    }

    /**
     * Gets a selector for an indicator element from headers if it exists, or returns fallback param
     * @param  string|null $indicator Optional fallback indicator
     */
    private function indicatorSelector(?string $indicator = null): ?string
    {
        return $this->getHeaderValue(self::INDICATOR_REQUEST_HEADER) ?? $indicator;
    }

    /**
     * Gets a header by name. Is case insensitive to account for potential differences in
     * server environments
     *
     * @param string $headerName Name of header
     * @param mixed  $default    Default value if header does not exist
     */
    private function getHeaderValue(string $headerName, mixed $default = null): mixed
    {
        if (!$this->requestHeaders) {
            $requestHeaders = getallheaders();

            $this->requestHeaders = array_change_key_case($requestHeaders, CASE_LOWER);
        }

        $headerName = strtolower($headerName);

        return $this->requestHeaders[$headerName] ?? $default;
    }
}
