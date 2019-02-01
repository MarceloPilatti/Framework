<?php

namespace Framework;

abstract class RuleType
{
    const REQUIRED = 'required';
    const UNIQUE = 'unique';
    const DEFAULT = 'default';
    const FOREIGN_KEY = 'foreign-key';
    const MIN = 'min';
    const MAX = 'max';
    const INT = 'int';
    const FLOAT = 'float';
    const DATE = 'date';
    const DATETIME = 'datetime';
    const FILE = 'file';
    const HTML = 'html';
    const PASSWORD = 'password';
    const PHONE = 'phone';
    const EMAIL = 'email';
    const CONFIRM = 'confirm';
    const CPF = 'cpf';
    const CNPJ = 'cnpj';
    const MONEY = 'money';
    const NORMAL_CHARS = 'normal-chars';
    const URL = 'url';
    const SLUG = 'slug';
}
