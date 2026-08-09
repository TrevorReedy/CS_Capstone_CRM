<?php
namespace App\Core;

/**
 * Turns a caught exception into something safe to send to a browser.
 *
 * The JSON list endpoints used to do this:
 *
 *     echo json_encode(['error' => true, 'message' => $e->getMessage()]);
 *
 * which hands the client raw PDO text — table names, column names, the failing
 * SQL, sometimes the database host. That is free reconnaissance for anyone
 * probing the app, and it is useless to the user, who cannot act on it.
 *
 * Instead: log the real message with a short random id, and return only that id.
 * Whoever hits the error can quote it, and it maps to one line in
 * storage/logs/application.log.
 */
class ErrorResponse
{
    /**
     * Log $e and return the request id to show the user.
     */
    public static function log(\Throwable $e, string $context = ''): string
    {
        $requestId = bin2hex(random_bytes(4));

        error_log(sprintf(
            '[%s]%s %s in %s:%d',
            $requestId,
            $context !== '' ? ' ' . $context : '',
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        return $requestId;
    }

    /**
     * Log $e, set a 500, and emit the standard JSON error envelope. The `error`
     * and `message` keys are kept because the DataTables error handlers already
     * read them.
     */
    public static function json(\Throwable $e, string $context = ''): void
    {
        $requestId = self::log($e, $context);

        http_response_code(500);
        echo json_encode([
            'error'      => true,
            'message'    => 'The request could not be completed. Reference: ' . $requestId,
            'request_id' => $requestId,
        ]);
    }

    /**
     * Log $e and return a user-facing sentence for a flash message.
     */
    public static function flash(\Throwable $e, string $action, string $context = ''): string
    {
        $requestId = self::log($e, $context);

        return "Could not {$action}. Please try again — reference {$requestId}.";
    }
}
