<?php

declare(strict_types=1);

namespace EzPhp\Validation;

use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\TranslatorInterface;

/**
 * Class FormRequest
 *
 * Abstract base for self-validating request objects.
 * Validates on construction — a successfully constructed instance is always valid.
 *
 * Usage:
 *   class CreatePostRequest extends FormRequest
 *   {
 *       public function rules(): array
 *       {
 *           return [
 *               'title' => ['required', 'string', 'max:255'],
 *               'body'  => ['required', 'string'],
 *           ];
 *       }
 *   }
 *
 *   $req = new CreatePostRequest($request->all());
 *   $data = $req->validated(); // only the fields declared in rules()
 *
 * Override authorize() to add authorization logic (default: true).
 * Override the constructor to inject DatabaseInterface or TranslatorInterface.
 *
 * @package EzPhp\Validation
 */
abstract class FormRequest
{
    /**
     * @param array<string, mixed> $data
     *
     * @throws AuthorizationException When authorize() returns false.
     * @throws ValidationException    When validation fails.
     */
    public function __construct(
        private readonly array $data,
        private readonly ?DatabaseInterface $db = null,
        private readonly ?TranslatorInterface $translator = null,
    ) {
        if (!$this->authorize()) {
            throw new AuthorizationException();
        }

        Validator::make($this->data, $this->rules(), $this->db, $this->translator)->validate();
    }

    /**
     * The validation rules for this request.
     *
     * @return array<string, string|list<string|RuleInterface|ConditionalRule>>
     */
    abstract public function rules(): array;

    /**
     * Determine if the current user is authorized to make this request.
     * Override to add authorization logic. Default: true.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return only the fields that appear in rules(), keyed by their top-level segment.
     * Extra submitted fields that are not in rules() are excluded.
     *
     * For nested rules ('address.city'), the top-level key ('address') is included.
     *
     * @return array<string, mixed>
     */
    public function validated(): array
    {
        $topLevelKeys = [];

        foreach (array_keys($this->rules()) as $key) {
            $topLevelKeys[explode('.', (string) $key)[0]] = true;
        }

        return array_intersect_key($this->data, $topLevelKeys);
    }

    /**
     * Return all submitted data, including fields not in rules().
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Get a specific field value from the submitted data.
     *
     * @param mixed $default
     *
     * @return mixed
     */
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }
}
