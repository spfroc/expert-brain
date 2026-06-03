<?php

namespace App\Http\Requests\Knowledge;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class KnowledgeDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'knowledge_base_id' => ['required', 'integer', 'exists:knowledge_bases,id'],
            'category_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
            'title' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'source_type' => ['nullable', Rule::in(['manual', 'policy', 'platform_doc', 'notice', 'case', 'faq', 'url', 'file'])],
            'source_url' => ['nullable', 'string', 'max:2000'],
            'version' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'expired', 'archived'])],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date'],
            'metadata' => ['nullable', 'array'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:knowledge_tags,id'],
        ];
    }
}
