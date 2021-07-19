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
    public function scopeFromRequest(Builder $query, Request $request, $namespace = null): Builder
    {
        if(is_null($request)) {
            return $query;
        }

        if(is_null($namespace)) {
            $namespace = $this->requestNameSpace;
        }

        /// This will only work on resources for now
        ///
        /// This whole thing needs to be moved into a sub query just for the orWhereHas() stuff, I dont like that it has the foreach $namespace.where within the loop
        foreach ($request->input("$namespace.below", []) as $key => $value) {
            if (in_array($key, $this->relationships)) {
                $whereHas = $key;
                $depth = 3;
                for ($d = 0; $depth >= $d; $d++) {
                    if($d == $depth) {
                        $query = $query->whereHas(
                            $whereHas,
                            function (Builder $sub_query) use ($request, $namespace, $key) {
                                $sub_query->fromRequest($request, "$namespace.below.$key");
                            }
                        );
                    } else {
                        $query = $query->orWhereHas(
                            $whereHas,
                            function (Builder $sub_query) use ($request, $namespace, $key) {
                                $sub_query->fromRequest($request, "$namespace.below.$key");
                            }
                        );

                        foreach ($request->input("$namespace.where", []) as $key2 => $value2) {
                            if ($this->isValidField($key2)) {
                                $query = $query->where($key2, $value2);
                            }
                        }
                    }
                    $whereHas = $whereHas . '.' . $key;
                }
            }
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

        // Bool filters
        foreach ($request->input("$namespace.is", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->where($key, boolval($value));
            }
        }

        // Null filters
        foreach ($request->input("$namespace.null", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->whereNull($key);
            }
        }

        foreach ($request->input("$namespace.not_null", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->whereNotNull($key);
            }
        }

        // Date filters
        foreach ($request->input("$namespace.before", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->where($key, "<=", Carbon::parse($value));
            }
        }
        foreach ($request->input("$namespace.after", []) as $key => $value) {
            if ($this->isValidField($key)) {
                $query = $query->where($key, ">=", Carbon::parse($value));
            }
        }

        // Geolocation filters
        $lat = $request->input("$namespace.within.lat");
        $lng = $request->input("$namespace.within.lng");
        $distance = $request->input("$namespace.within.distance");
        $measurement = $request->input("$namespace.within.measurement", "miles");
        if(!is_null($lat) && !is_null($lng) && !is_null($distance) && in_array($measurement, ["miles", "m", "kilometers", "km", "nautical_miles", "feet"])) {
            $query = $query->within($distance, $measurement, $lat, $lng);
        }

        // Relationship checkers
        foreach ($request->input("$namespace.has", []) as $key => $value) {
            if (in_array($key, $this->relationships)) {
                if($key === "user" && $value === "me") {
                    $query = $query->where('user_id', Auth::id());
                } else if(is_numeric($value)) {
                    $query = $query->where($key . '_id', $value);
                } else {
                    $query = $query->whereHas(
                        $key,
                        function (Builder $sub_query) use ($request, $namespace, $key) {
                            $sub_query->fromRequest($request, "$namespace.has_not.$key");
                        }
                    );
                }
            }
        }

        foreach ($request->input("$namespace.has_not", []) as $key => $value) {
            if (in_array($key, $this->relationships)) {
                if($key === "user" && $value === "me") {
                    $query = $query->where('user_id', '!=', Auth::id());
                } else if(is_numeric($value)) {
                    $query = $query->where($key . '_id', '!=', $value);
                } else {
                    $query = $query->whereDoesntHave(
                        $key,
                        function (Builder $sub_query) use ($request, $namespace, $key) {
                            $sub_query->fromRequest($request, "$namespace.has_not.$key");
                        }
                    );
                }
            }
        }

        // Ordering
        $column = $request->input("$namespace.order.column", "id");
        $order = $request->input("$namespace.order.direction", "asc");
        if ($this->isValidField($column) && in_array($order, ["asc", "desc"])) {
            $query = $query->orderBy($column, $order);
        }
        if ($order == "rnd") {
            $query = $query->inRandomOrder();
        }

        return $query;
    }
}
