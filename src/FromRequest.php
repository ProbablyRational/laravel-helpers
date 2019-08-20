<?php

namespace ProbablyRational\LaravelHelpers;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

trait FromRequest
{
    /**
     * Check if field is valid on object.
     *
     * @param  $field
     * @return bool
     */
    private function isValidField($field): bool
    {
        return in_array($field, $this->fillable) || Str::startsWith($field, 'meta->') || Str::endsWith($field, 'id');
    }

    /**
     * Scope a query to only include models that match the request params.
     *
     * @param Builder $query
     * @param Request $request
     * @return Builder
     */
    public function scopeFromRequest(Builder $query, Request $request): Builder
    {
        if(is_null($request)) {
            return $query;
        }

        // String filters
        foreach ($request->input("$namespace.where", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->where($key, $value);
            }
        }

        return $query;
    }
}
