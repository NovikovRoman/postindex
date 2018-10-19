<?php

namespace PostIndex;

use GuzzleHttp\Client;
use XBase\Table;

class PostIndex
{
    const URL = 'http://vinfo.russianpost.ru/database/';
    const FILENAME = 'PIndx.zip';
    const FILENAME_DBF = 'post-index.dbf';
    const FILENAME_CSV = 'post-index.csv';
    const DIR_MODE = 0700;
    const CSV_DELIMITER = ';';
    /** @var \DateTime */
    private $lastModified;
    private $httpClient;
    private $pathDir;
    /** @var \DateTime */
    private $lastModifiedOnWebsite;
    /**
     * @var array
     * Описание столбцов. Их порядок сохранится в CSV
     * index     Почтовый индекс объекта почтовой связи в соответствии с действующей системой индексации
     * opsname   Наименование объекта почтовой связи
     * opstype   Тип объекта почтовой связи
     * opssubm   Индекс вышестоящего по иерархии подчиненности объекта почтовой связи
     * region    Наименование области, края, республики, в которой находится объект почтовой связи
     * autonom   Наименование автономной области, в которой находится объект почтовой связи
     * area      Наименование района, в котором находится объект почтовой связи
     * city      Наименование населенного пункта, в котором находится объект почтовой связи
     * city_1    Наименование подчиненного населенного пункта, в котором находится объект почтовой связи
     * actdate   Дата актуализации информации об объекте почтовой связи
     * indexold  Почтовый индекс объект почтовой связи до ввода действующей системы индексации
     */
    private $colsDBF = ['index', 'opsname', 'opstype', 'opssubm', 'region', 'autonom', 'area', 'city', 'city_1', 'actdate', 'indexold'];

    /**
     * PostIndex constructor.
     * @param string $pathDir Куда сохраняем данные
     * @param \DateTime|null $lastModified дата нашей версии
     * @throws PostIndexException
     */
    public function __construct($pathDir, \DateTime $lastModified = null)
    {
        $this->lastModified = $lastModified;
        if (file_exists($pathDir) && is_file($pathDir)) {
            throw new PostIndexException($pathDir . ' not directory');
        } elseif (!file_exists($pathDir) && !mkdir($pathDir, self::DIR_MODE, true)) {
            throw new PostIndexException($pathDir . ' directory not created');
        }
        $pathDir .= mb_substr($pathDir, -1, 1, 'UTF-8') == '/' ? '' : '/';
        $this->pathDir = $pathDir;
    }

    public function filepathCSV()
    {
        if (file_exists($this->pathDir . self::FILENAME_CSV)) {
            return $this->pathDir . self::FILENAME_CSV;
        }
        return false;
    }

    public function filepathDBF()
    {
        if (file_exists($this->pathDir . self::FILENAME_DBF)) {
            return $this->pathDir . self::FILENAME_DBF;
        }
        return false;
    }

    /**
     * @return bool
     * @throws PostIndexException
     */
    public function hasNewVersion()
    {
        if (empty($this->httpClient)) {
            $this->httpClient = new Client();
        }
        $response = $this->httpClient->head(self::URL);
        $lastModified = $response->getHeader('Last-Modified');
        if (empty($lastModified)) {
            throw new PostIndexException('not connected');
        }
        if (is_array($lastModified)) {
            $lastModified = array_pop($lastModified);
        }
        $this->lastModifiedOnWebsite = new \DateTime($lastModified);
        return $this->lastModified < $this->lastModifiedOnWebsite;
    }

    /**
     * @return \DateTime
     * @throws PostIndexException
     */
    public function lastModifiedOnWebsite()
    {
        if (empty($this->lastModifiedOnWebsite)) {
            $this->hasNewVersion();
        }
        return $this->lastModifiedOnWebsite;
    }

    /**
     * @param string $csvDelimiter
     * @param bool $cp1251
     * @return PostIndex
     * @throws PostIndexException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function refresh($csvDelimiter = self::CSV_DELIMITER, $cp1251 = false)
    {
        $this->download()
            ->unzip();
        $table = new Table($this->pathDir . self::FILENAME_DBF, $this->colsDBF, 'cp866');
        $fh = fopen($this->pathDir . self::FILENAME_CSV, 'w+');
        fputcsv($fh, $this->colsDBF, $csvDelimiter);
        while ($record = $table->nextRecord()) {
            $row = [];
            foreach ($this->colsDBF as $colName) {
                if ($colName == 'actdate') {
                    $val = date('d.m.Y', $record->getDate($colName));
                } else {
                    $val = $cp1251 ? $this->utf8ToCp1251($record->getString($colName)) : $record->getString($colName);
                }
                $row[] = $val;
            }
            fputcsv($fh, $row, $csvDelimiter);
        }
        fclose($fh);
        return $this;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function download()
    {
        if (empty($this->httpClient)) {
            $this->httpClient = new Client();
        }
        $response = $this->httpClient->request('GET', self::URL . self::FILENAME, ['stream' => true]);
        $body = $response->getBody();
        $fh = fopen($this->pathDir . self::FILENAME, 'wb+');
        while (!$body->eof()) {
            fwrite($fh, $body->read(1024));
        }
        fclose($fh);
        return $this;
    }

    /**
     * @throws PostIndexException
     */
    private function unzip()
    {
        $zip = new \ZipArchive();
        if ($zip->open($this->pathDir . self::FILENAME) === TRUE) {
            $zip->extractTo($this->pathDir);
            $filename = $zip->getNameIndex(0);
            $zip->close();
            unlink($this->pathDir . self::FILENAME);
            rename($this->pathDir . $filename, $this->pathDir . self::FILENAME_DBF);
        } else {
            throw new PostIndexException('unzip error');
        }
        return $this;
    }

    private function utf8ToCp1251($text)
    {
        return iconv('utf-8', 'cp1251', $text);
    }
}