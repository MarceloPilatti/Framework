<?php

namespace Lib;

abstract class ApplicationError
{
    public static function showError(\Throwable $throwable, int $type)
    {
        if ($type == ErrorType::NOTFOUND) {
            $view = new View('error/error-page');
            return $view->render();
        } elseif ($type == ErrorType::ERROR) {
            $message = null;
            if (getenv("APPLICATION_ENV") == "development") {
                if ($throwable) {
                    $message = "<strong style='font-size:18px'>An error occurred: " . $throwable->getMessage() . " on file " . $throwable->getFile() . ", line " . $throwable->getLine() . "</strong><br /><br />";
                    $trace = $throwable->getTrace();
                    $message .= "<div class='table-overflow'><table class='table table-bordered nomargin'><thead><th>File</th><th>Line</th><th>Action</th><th>Class</th><th>Type</th></thead></tbody>";
                    if ($trace) {
                        foreach ($trace as $row) {
                            $message .= "<tr>";
                            foreach ($row as $cell) {
                                if (is_array($cell)) {
                                    continue;
                                }
                                $message .= "<td>" . $cell . "</td>";
                            }
                            $message .= "</tr>";
                        }
                    }
                    $message .= "</tbody></table></div>";
                }
            }
            $view = new View('error/error-page', ["message" => $message]);
            return $view->render();
        }
    }
}