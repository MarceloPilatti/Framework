<?php

namespace Framework;

use DateTime;
use Intervention\Image\ImageManagerStatic as Image;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;

class Validator
{
    private $request;
    private $entityNames;
    private $entities = [];

    public function __construct(Request $request, $entityNames)
    {
        $this->request = $request;
        $this->entityNames = $entityNames;
    }

    public function validateForm()
    {
        $session = new Session();
        $errors = null;
        $inputs = null;
        $entityNamesArray = $this->entityNames;
        $request = $this->request;
        $formData=array_merge($request->query->all(), $request->files->all(), $request->attributes->all(), $request->request->all());
        $lastInsertedIds = [];
        $entityMultipleFiles = [];
        $file=false;
        $many=false;
        $entities=[];
        $fileToDelete="";
        $thumbToDelete="";
        $entityValues = [];
        $entityForeignKeys=[];
        $isTransaction=false;
        $prefixes=[];
        $fKOneName="";
        $exceptions=["date", "datetime", "default", "slug", "checkbox", "foreign-key:many"];
        foreach ($entityNamesArray as $count => $entityName) {
            $entityRuleArray = $entityName::rules();
            $entityClass = substr(strrchr($entityName, "\\"), 1);
            $entityDAOName = 'Main\\DAO\\' . $entityClass . 'DAO';
            $entityClass = lcfirst($entityClass);
            $entityClassId=$entityClass."Id";
            $entityId=$formData[$entityClassId];
            $entityDAO = new $entityDAOName;
            if(count($entityNamesArray)>1 && $count==0){
                $isTransaction=true;
                $entityDAO->begin();
            }
            $entity = $entityDAO->getById($entityId);
            if ($entity) {
                $entityValues = $entity->getAttrs();
            }
            if (!$entity) {
                $entity = new $entityName;
            }
            foreach ($entityRuleArray as $ruleKey => $entityRule) {
                $rules = $entityRule["rules"];
                $rulesArray = [$rules];
                if (strpos($rules, "|") !== false) {
                    $rulesArray = explode("|", $rules);
                }
                if(!array_key_exists($ruleKey, $formData) && self::checkExceptions($exceptions, $rules)===false){
                    continue;
                }
                $data = $formData[$ruleKey];
                $msgError = "";
                foreach ($rulesArray as $rule) {
                    if (strpos($rule, ":") !== false) {
                        $ruleInfo = explode(":", $rule);
                        list($ruleDesc, $ruleValue)=$ruleInfo;
                        if ($ruleDesc === RuleType::MIN) {
                            $msgError .= self::validateMaxMin($data, $ruleValue, RuleType::MIN);
                        } else if ($ruleDesc === RuleType::MAX) {
                            $msgError .= self::validateMaxMin($data, $ruleValue, RuleType::MAX);
                        } else if ($ruleDesc === RuleType::DEFAULT) {
                            $entityValues[$ruleKey] = $ruleValue;
                        } else if ($ruleDesc === RuleType::SLUG) {
                            $stringToSlugify=$formData[$ruleValue];
                            $slug=Slugify::get($stringToSlugify);
                            $entityValues[$ruleKey] = $slug;
                        } else if ($ruleDesc === RuleType::NORMAL_CHARS) {
                            $specialCharsValidated = self::validateSpecialChars($data, $ruleValue);
                            $msgError .= $specialCharsValidated['msgError'];
                            $entityValues[$ruleKey] = $specialCharsValidated['data'];
                        } else if ($ruleDesc === RuleType::FOREIGN_KEY) {
                            if ($ruleValue === 'one') {
                                if (!$data) {
                                    $data = $lastInsertedIds[$ruleKey][0];
                                }
                                $fKOneName=$ruleKey;
                                $entityValues[$ruleKey] = $data;
                            } else if ($ruleValue === 'many') {
                                if(array_key_exists($ruleKey, $lastInsertedIds)){
                                    $fKValues=$lastInsertedIds[$ruleKey];
                                }else{
                                    $fKValues = $data;
                                }
                                $many = true;
                                if($fKValues) {
                                    foreach ($fKValues as $count => $fKValue) {
                                        $entityForeignKeys[$count][$ruleKey] = $fKValue;
                                    }
                                }
                                $entityValues[$ruleKey] = null;
                            }
                        }
                    } else {
                        switch ($rule) {
                            case RuleType::REQUIRED:
                                $msgError .= self::validateEmpty($data);
                                $entityValues[$ruleKey] = $data;
                                break;
                            case RuleType::UNIQUE:
                                $msgError .= self::validateUnique($data, $entityDAO, $entityId, $ruleKey);
                                break;
                            case RuleType::FLOAT:
                                $isFloatValidated = self::validateFloat($data);
                                $msgError .= $isFloatValidated['msgError'];
                                $entityValues[$ruleKey] = $isFloatValidated['data'];
                                break;
                            case RuleType::INT:
                                $isIntValidated = self::validateInt($data);
                                $msgError .= $isIntValidated['msgError'];
                                $entityValues[$ruleKey] = $isIntValidated['data'];
                                break;
                            case RuleType::CHECKBOX:
                                $isIntValidated = self::validateInt($data);
                                $msgError .= $isIntValidated['msgError'];
                                $entityValues[$ruleKey] = $isIntValidated['data'];
                                break;
                            case RuleType::EMAIL:
                                $data = trim($data);
                                $msgError .= self::validateEmail($data);
                                $entityValues[$ruleKey] = $data;
                                break;
                            case RuleType::HTML:
                                $entityValues[$ruleKey] = htmlentities($data);
                                break;
                            case RuleType::DATETIME:
                                $dateValidated = self::validateDate($data, RuleType::DATETIME);
                                $msgError .= $dateValidated['msgError'];
                                $entityValues[$ruleKey] = $dateValidated['data'];
                                break;
                            case RuleType::DATE:
                                $dateValidated = self::validateDate($data, RuleType::DATE);
                                $msgError .= $dateValidated['msgError'];
                                $entityValues[$ruleKey] = $dateValidated['data'];
                                break;
                            case RuleType::PHONE:
                                $data = trim($data);
                                $phone = $data;
                                $phone = str_replace(['(', ')', ' ', '-'], "", $phone);
                                $entityValues[$ruleKey] = $phone;
                                break;
                            case RuleType::MONEY:
                                $money = $data;
                                $money = str_replace(['R$ ', '.'], "", $money);
                                $money = str_replace(',', ".", $money);
                                $entityValues[$ruleKey] = $money;
                                break;
                            case RuleType::PASSWORD:
                                $data = trim($data);
                                $password = $data;
                                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                                $entityValues[$ruleKey] = $passwordHash;
                                break;
                            case RuleType::CONFIRM:
                                $fieldData = trim($data);
                                $fieldKey = $ruleKey;
                                $confirmFieldName = 'confirm' . ucfirst($fieldKey);
                                if (array_key_exists($confirmFieldName, $formData)) {
                                    $confirmFieldData = $formData[$confirmFieldName];
                                    if ($fieldData !== $confirmFieldData) {
                                        $msgError .= 'Os valores não conferem.';
                                    }
                                    $inputs[$confirmFieldName] = $confirmFieldData;
                                }
                                break;
                            case RuleType::FILE:
                                $prefix = $entityRule["prefix"]??"";
                                array_push($prefixes, $prefix);
                                if($entityId){
                                    $fileNameIndex=$prefix?$prefix."FileName":"fileName";
                                    $filePathIndex=$prefix?$prefix."FilePath":"filePath";
                                    $fileToDelete=$entity->$filePathIndex.$entity->$fileNameIndex;
                                    $thumbToDelete=$entity->$filePathIndex . 'thumb/' . $entity->$fileNameIndex;
                                }
                                $file=true;
                                $validExtensions = $entityRule["extensions"]??'';
                                $isImage = $entityRule["isImage"]??0;
                                $imgSize = $entityRule["size"]??null;
                                $files = $data;
                                if(!$files){
                                    break;
                                }
                                if (!is_array($files)) {
                                    $files = [$files];
                                }
                                $filesValidated=self::validateFiles($files, $validExtensions, $entityClass, $isImage, $imgSize, $prefix, $entityMultipleFiles);
                                $entityMultipleFiles=$filesValidated["data"];
                                $msgError.=$filesValidated["msgError"];
                                break;
                            case RuleType::CPF:
                                $data = trim($data);
                                $cpf = $data;
                                if (!self::validateCPF($cpf)) {
                                    $msgError .= "CPF inválido.<br />";
                                }
                                $cpf = str_replace(['.', '-'], "", $cpf);
                                $entityValues[$ruleKey] = $cpf;
                                break;
                            case RuleType::CNPJ:
                                $data = trim($data);
                                $cnpj = $data;
                                if (!self::validateCNPJ($cnpj)) {
                                    $msgError .= "CNPJ inválido.<br />";
                                }
                                $cnpj = str_replace(['.', '-', '/'], "", $cnpj);
                                $entityValues[$ruleKey] = $cnpj;
                                break;
                            case RuleType::URL:
                                $url = trim($data);
                                if(!$url){
                                    break;
                                }
                                $urlValidated=self::validateUrl($url);
                                $entityValues[$ruleKey] = $urlValidated["data"];
                                $msgError.=$urlValidated["msgError"];
                                break;
                        }
                    }
                }
                if ($msgError) {
                    $errors[$ruleKey] = $msgError;
                }
                $inputs[$ruleKey] = $data;
            }
            if ($errors) {
                $session->set('errors', $errors);
                $session->set('inputs', $inputs);
                if($isTransaction){
                    $entityDAO->rollback();
                }
                return 1;
            } else {
                $session->set('errors', null);
                $session->set('inputs', null);
                if ($file) {
                    unset($entityValues['file']);
                    $entities = [];
                    if ($entityMultipleFiles) {
                        foreach ($entityMultipleFiles as $countFile => $entityMultipleFile) {
                            $prefix=$prefixes[$countFile];
                            if(!$entityId) {
                                $entity = new $entityName;
                            }
                            $originalNameIndex=$prefix?$prefix.'FileOriginalName':'fileOriginalName';
                            $fileNameIndex=$prefix?$prefix.'FileName':'fileName';
                            $filePathIndex=$prefix?$prefix.'FilePath':'filePath';
                            $entityValues[$originalNameIndex] = $entityMultipleFile[$originalNameIndex];
                            $entityValues[$fileNameIndex] = $entityMultipleFile[$fileNameIndex];
                            $entityValues[$filePathIndex] = $entityMultipleFile[$filePathIndex];
                            $entity->setAttrs($entityValues);
                            if($entityId){
                                $updated = $entityDAO->update($entity);
                                unlink($fileToDelete);
                                unlink($thumbToDelete);
                                if (!$updated) {
                                    if($isTransaction){
                                        $entityDAO->rollback();
                                    }
                                    return 2;
                                }
                            }else{
                                $entity = $entityDAO->insert($entity);
                                if (!$entity) {
                                    if($isTransaction){
                                        $entityDAO->rollback();
                                    }
                                    return 2;
                                }
                            }
                            $entities[$entityClass][$countFile] = $entity;
                            $lastInsertedIds[$entityClass."Id"][$countFile]=$entity->id;
                        }
                    }
                    $file=false;
                } else {
                    if ($many) {
                        $entities = [];
                        if ($entityForeignKeys) {
                            if($fKOneName) {
                                $fKOneId = $lastInsertedIds[$fKOneName];
                                $allDeleted = $entityDAO->deleteBy([$fKOneName => $fKOneId]);
                                if (!$allDeleted) {
                                    $entityDAO->rollback();
                                    return 2;
                                }
                            }
                            $count = 0;
                            foreach ($entityForeignKeys as $eFK) {
                                foreach ($eFK as $key=>$entityForeignKey) {
                                    $entityValues[$key] = $entityForeignKey;
                                }
                                $entity->setAttrs($entityValues);
                                $entity = $entityDAO->insert($entity);
                                if (!$entity) {
                                    if($isTransaction){
                                        $entityDAO->rollback();
                                    }
                                    return 2;
                                }
                                $entities[$entityClass.'Id'][$count] = $entity;
                                $count++;
                            }
                            $entityForeignKeys=[];
                        }
                        $many=false;
                        $fKOneName="";
                    } else {
                        $entity->setAttrs($entityValues);
                        if ($entityId) {
                            $result = $entityDAO->update($entity);
                            if (!$result) {
                                if($isTransaction){
                                    $entityDAO->rollback();
                                }
                                return 2;
                            }
                        } else {
                            $entity = $entityDAO->insert($entity);
                            if (!$entity) {
                                if($isTransaction){
                                    $entityDAO->rollback();
                                }
                                return 2;
                            }
                        }
                        $entityId = null;
                        $lastInsertedIds[$entityClass.'Id']=$entity->id;
                        $entities[$entityClass] = $entity;
                    }
                }
            }
            $entityValues=[];
        }
        if($isTransaction){
            $entityDAO->commit();
        }
        $this->entities = $entities;
        return true;
    }

    public static function checkExceptions($exceptions, $rules){
        foreach ($exceptions as $exception){
            if(strpos($rules, $exception) !== false){
                return true;
            }
        }
        return false;
    }

    public static function validateMaxMin($data, $ruleValue, $ruleType)
    {
        $msgError = '';
        if ($ruleType == RuleType::MIN) {
            $data = trim($data);
            if (strlen($data) < $ruleValue) {
                $msgError .= "São aceitos no mínimo " . $ruleValue . " caracteres.<br />";
            }
        } else {
            if ($ruleType == RuleType::MAX) {
                $data = trim($data);
                if (strlen($data) > $ruleValue) {
                    $msgError .= "São aceitos no máximo " . $ruleValue . " caracteres.<br />";
                }
            }
        }
        return $msgError;
    }

    public static function validateUrl($url)
    {
        $msgError="";
        $result=[];
        $path = parse_url($url, PHP_URL_PATH);
        $encoded_path = array_map('urlencode', explode('/', $path));
        $url = str_replace($path, implode('/', $encoded_path), $url);
        $filteredUrl=Filter::filterUrl($url);
        if($filteredUrl===false){
            $msgError .= "Link inválido.<br />";
        }
        $validatedUrl = filter_var($url, FILTER_VALIDATE_URL);
        if($validatedUrl===false){
            $msgError .= "Link inválido.<br />";
        }
        $result["data"]=$url;
        $result["msgError"]=$msgError;
        return $result;
    }

    public static function validateSpecialChars($data, $ruleValue)
    {
        $result = [];
        $msgError = '';
        $data = trim($data);
        $exceptions = $ruleValue;
        if (strpos($ruleValue, ';')) {
            $exceptions = explode(";", $ruleValue);
        }
        $string = $data;
        $tempString = str_replace($exceptions, '', $string);
        if (preg_match('/[^a-zA-Z\d]/', $tempString)) {
            $msgError .= "Não são permitidos caracteres especiais";
            if ($exceptions) {
                $msgError .= " exceto ";
                foreach ($exceptions as $count => $exception) {
                    $msgError .= $exception;
                    if (count($exceptions) !== $count) {
                        $msgError .= ', ';
                    }
                }
            }
        }
        $result['data'] = $string;
        $result['msgError'] = $msgError;
        return $result;
    }

    public static function validateEmpty($data)
    {
        $msgError = '';
        if ($data == '' || $data==null || $data === '<p>&nbsp;</p>') {
            $msgError .= "Este campo é obrigatório.<br />";
        }
        return $msgError;
    }

    public static function validateUnique($data, $entityDAO, $entityId, $ruleKey)
    {
        $msgError = '';
        $checkUnique = $entityDAO->getBy([$ruleKey => $data], null, null, null, null, true);
        if ($checkUnique && $checkUnique->id != $entityId) {
            $msgError .= "Já cadastrado. Escolha outro.<br />";
        }
        return $msgError;
    }

    public static function validateFloat($data)
    {
        $result = [];
        $msgError = '';
        $data=str_replace('.', '', $data);
        $data=str_replace(',', '.', $data);
        if ($data) {
            if (!is_numeric($data)) {
                $msgError .= "Digite somente números decimais.<br />";
            }
        }
        $result['data'] = $data;
        $result['msgError'] = $msgError;
        return $result;
    }

    public static function validateInt($data)
    {
        $result = [];
        $msgError = '';
        if (!$data) {
            $data = '0';
        }
        if (!is_numeric($data)) {
            $msgError .= "Digite somente números.<br />";
        }
        $result['data'] = $data;
        $result['msgError'] = $msgError;
        return $result;
    }

    public static function validateEmail($data)
    {
        $msgError = '';
        $data = trim($data);
        if ($data) {
            if (!filter_var($data, FILTER_VALIDATE_EMAIL)) {
                $msgError .= "Email inválido.<br />";
            }
        }
        return $msgError;
    }

    public static function validateDate($data, $ruleType)
    {
        $data = trim($data);
        $result = [];
        $msgError = '';
        $inputFormat='';
        $storeFormat='';
        if ($ruleType == RuleType::DATETIME) {
            $inputFormat = 'd/m/Y H:i:s';
            $storeFormat = 'Y-m-d H:i:s';
        } else {
            if ($ruleType == RuleType::DATE) {
                $inputFormat = 'd/m/Y';
                $storeFormat = 'Y-m-d';
            }
        }
        if ($data) {
            $verifierDate = DateTime::createFromFormat($inputFormat, $data);
            if ($verifierDate && $verifierDate->format($inputFormat) == $data) {
                $msgError .= "Data inválida.<br />";
            }
            $date = $data;
            $date = new \DateTime(str_replace('/', '-', $date), new \DateTimeZone("America/Sao_Paulo"));
            $data = $date->format($storeFormat);
        } else {
            $date = new \DateTime('now', new \DateTimeZone("America/Sao_Paulo"));
            $data = $date->format($storeFormat);
        }
        $result['data'] = $data;
        $result['msgError'] = $msgError;
        return $result;
    }

    public static function validateFiles($files, $validExtensions, $entityClass, $isImage, $imgSize, $prefix,$entityMultipleFiles=[])
    {
        $result=[];
        $msgError="";
        $entityMultipleFilesCount=count($entityMultipleFiles);
        foreach ($files as $file) {
            $isValid = self::validateFile($file, $validExtensions);
            if ($isValid !== true) {
                $msgError .= $isValid;
            }
            $fileOriginalName = $file->getClientOriginalName();
            $tempName = $file->getPathname();
            $fullDestPath = './data/uploads/' . $entityClass . '/';
            if (!is_dir($fullDestPath)) {
                mkdir($fullDestPath, 0777, true);
            }
            if ($isImage == 1) {
                $fullDestThumbPath = $fullDestPath . 'thumb/';
                if (!is_dir($fullDestThumbPath)) {
                    mkdir($fullDestThumbPath, 0777, true);
                }
            }
            $filePath = $fullDestPath;
            $fileExtension = pathinfo($fileOriginalName, PATHINFO_EXTENSION);
            $fileName = md5(uniqid(rand(), true) . time()) . '.' . $fileExtension;
            $destFileName = $fullDestPath . $fileName;

            if ($isImage == 1) {
                $fullDestThumbPath = $fullDestPath . 'thumb/';
                if (!is_dir($fullDestThumbPath)) {
                    mkdir($fullDestThumbPath, 0777, true);
                }
                $destThumbName = $fullDestThumbPath . $fileName;
                $img = Image::make($tempName);
                if ($imgSize) {
                    $imgSize = explode('x', $imgSize);
                    list($width, $height)=$imgSize;
                    $img->fit($width, $height);
                }
                $img = $img->save($destFileName, 75);
                if (!$img) {
                    $msgError .= 'Erro ao salvar a imagem.<br />';
                }
                $img = Image::make($tempName)->fit(200)->save($destThumbName);
                if (!$img) {
                    $msgError .= 'Erro ao salvar a miniatura.<br />';
                }
            } else {
                move_uploaded_file($tempName, $destFileName);
            }
            $originalNameIndex=$prefix?$prefix.'FileOriginalName':'fileOriginalName';
            $fileNameIndex=$prefix?$prefix.'FileName':'fileName';
            $filePathIndex=$prefix?$prefix.'FilePath':'filePath';
            $entityMultipleFiles[$entityMultipleFilesCount][$originalNameIndex] = $fileOriginalName;
            $entityMultipleFiles[$entityMultipleFilesCount][$fileNameIndex] = $fileName;
            $entityMultipleFiles[$entityMultipleFilesCount][$filePathIndex] = $filePath;
            $entityMultipleFilesCount++;
        }
        $result["data"]=$entityMultipleFiles;
        $result["msgError"]=$msgError;
        return $result;
    }

    public static function validateFile($file, $validExtensions)
    {
        try {
            $fileName = $file->getClientOriginalName();
            $tempName = $file->getPathname();
            $fileSize = $file->getSize();
            $error = $file->getError();
            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
            if(!$validExtensions){
                $msg = "A extensão do arquivo " . $fileName . " é inválida.";
                return $msg;
            }
            $validExtensions = explode(';', $validExtensions);
            $msg = true;
            $postMaxSize = ini_get('upload_max_filesize');
            $postMaxSizeBytes = self::return_bytes($postMaxSize);
            if ($error) {
                switch ($error) {
                    case UPLOAD_ERR_INI_SIZE:
                        $msg = "O tamanho do arquivo " . $fileName . " excede o limite de " . $postMaxSize . "B.";
                        break;
                    case UPLOAD_ERR_FORM_SIZE:
                        $msg = "O tamanho do arquivo " . $fileName . " excede o limite de " . $postMaxSize . "B.";
                        break;
                    case UPLOAD_ERR_PARTIAL:
                        $msg = 'O upload do arquivo foi feito parcialmente.';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $msg = 'Nenhum arquivo foi enviado.';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $msg = 'Pasta temporária ausente.';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $msg = 'Falha em escrever o arquivo em disco.';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $msg = 'Uma extensão do PHP interrompeu o upload do arquivo.';
                        break;
                }
            }
            if (empty($fileName) && empty($tempName)) {
                $msg = "Nenhum arquivo foi selecionado.";
                return $msg;
            }
            if ($fileSize <= 0 || $fileSize > $postMaxSizeBytes) {
                $msg = "O tamanho do arquivo " . $fileName . " excede o limite de " . $postMaxSize . "B.";
                return $msg;
            }
            if (!in_array(strtolower($fileExtension), $validExtensions)) {
                $msg = "A extensão do arquivo " . $fileName . " é inválida.";
                return $msg;
            }
            return $msg;
        } catch (\Throwable $t) {
            return false;
        }
    }

    private static function return_bytes($val)
    {
        $val = trim($val);
        $last = substr($val, -1);
        $val = substr($val, 0, -1);
        switch ($last) {
            case 'G':
                $val *= 1024;
            case 'M':
                $val *= 1024;
            case 'K':
                $val *= 1024;
        }
        return $val;
    }

    private function validateCPF($cpf)
    {
        if (!$cpf) {
            return false;
        }
        $cpf = str_replace(".", "", $cpf);
        $cpf = str_replace("-", "", $cpf);
        if (strlen($cpf) != 11) {
            return false;
        }
        $sum = 0;
        if ($cpf == "12345678909" || $cpf == "00000000000" || $cpf == "11111111111" || $cpf == "22222222222" || $cpf == "33333333333" || $cpf == "44444444444" || $cpf == "55555555555" || $cpf == "66666666666" || $cpf == "77777777777" || $cpf == "88888888888" || $cpf == "99999999999") {
            return false;
        }
        for ($count = 1; $count <= 9; $count++) {
            $digit = $cpf[$count - 1];
            $mult = intval($digit) * (11 - $count);
            $sum += $mult;
        }
        $rest = ($sum * 10) % 11;
        if (($rest == 10) || ($rest == 11)) {
            $rest = 0;
        }
        if ($rest != intval($cpf[9])) {
            return false;
        }
        $sum = 0;
        for ($count = 1; $count <= 10; $count++) {
            $digit = $cpf[$count - 1];
            $mult = intval($digit) * (12 - $count);
            $sum += $mult;
        }
        $rest = ($sum * 10) % 11;
        if (($rest == 10) || ($rest == 11)) {
            $rest = 0;
        }
        if ($rest != intval($cpf[10])) {
            return false;
        }
        return true;
    }

    private function validateCNPJ($cnpj)
    {
        if (!$cnpj) {
            return false;
        }
        $cnpj = preg_replace('/[^0-9]/', '', (string)$cnpj);
        if (strlen($cnpj) != 14) {
            return false;
        }
        for ($i = 0, $j = 5, $sum = 0; $i < 12; $i++) {
            $sum += $cnpj{$i} * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        $rest = $sum % 11;
        if ($cnpj{12} != ($rest < 2 ? 0 : 11 - $rest)) {
            return false;
        }
        for ($i = 0, $j = 6, $sum = 0; $i < 13; $i++) {
            $sum += $cnpj{$i} * $j;
            $j = ($j == 2) ? 9 : $j - 1;
        }
        $rest = $sum % 11;
        return $cnpj{13} == ($rest < 2 ? 0 : 11 - $rest);
    }

    public function __get($name)
    {
        return $this->$name;
    }

    public function __set($name, $value)
    {
        $this->$name = $value;
    }
}