<?php

namespace Framework;

abstract class Pagination
{
    public static function getLimitAndOffset($page, $rowsPerPage)
    {
        $offset = null;
        $limit = $rowsPerPage;
        if ($page && $page != 1) {
            $offset = ($page - 1) * $limit;
        } else {
            $offset = 0;
        }
        return ['limit' => $limit, 'offset' => $offset];
    }
}
