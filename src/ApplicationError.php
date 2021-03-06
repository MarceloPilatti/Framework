<?php

namespace Framework;

abstract class ApplicationError
{
    public static function showError(\Throwable $throwable, int $type, $viewName='main/error/error-page')
    {
        $view=null;
        if ($type == ErrorType::NOTFOUND) {
            $view = new View($viewName);
        } elseif ($type == ErrorType::ERROR) {
            $message = null;
            if (getenv("APPLICATION_ENV") == "development") {
                if ($throwable) {
                    $message = "<strong style='font-size:18px'>An error occurred: " . $throwable->getMessage() . " on file " . $throwable->getFile() . ", line " . $throwable->getLine() . "</strong><br /><br />";
                    $trace = $throwable->getTrace();
                    $message .= "<div class='table-overflow'><table class='table table-bordered nomargin'><thead><tr><th>File</th><th>Line</th><th>Action</th><th>Class</th><th>Type</th></tr></thead></tbody>";
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
            $view = new View($viewName, ["message" => $message]);
        }
        return $view->render();
    }
}