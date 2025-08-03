<?php
class AppException extends Exception {
    public function __construct($message = "Erro na aplicação", $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }

    public function getErrorMessage() {
        return "Erro: {$this->message} na linha {$this->getLine()} do arquivo {$this->getFile()}";
    }

}
