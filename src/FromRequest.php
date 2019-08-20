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

        foreach ($request->input("$namespace.not", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->where($key, "!=", $value);
            }
        }

        foreach ($request->input("$namespace.like", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->where($key, "like", "%$value%");
            }
        }

        foreach ($request->input("$namespace.not_like", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->where($key, "not like", "%$value%");
            }
        }

        foreach ($request->input("$namespace.starts", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->where($key, "like", "$value%");
            }
        }

        foreach ($request->input("$namespace.not_starts", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->where($key, "not like", "$value%");
            }
        }

        foreach ($request->input("$namespace.ends", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->where($key, "like", "%$value");
            }
        }

        foreach ($request->input("$namespace.not_ends", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->where($key, "not like", "%$value");
            }
        }

        // Number filters
        foreach ($request->input("$namespace.lt", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->where($key, "<", intval($value));
            }
        }

        foreach ($request->input("$namespace.gt", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->where($key, ">", intval($value));
            }
        }

        foreach ($request->input("$namespace.lte", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->where($key, "<=", intval($value));
            }
        }

        foreach ($request->input("$namespace.gte", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->where($key, ">=", intval($value));
            }
        }

        return $query;
    }
}
