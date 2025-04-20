<?php

/**
 * Класс для обработки текстовых файлов Adblock Plus
 *
 * Этот класс предоставляет функционал для загрузки контента из URL-адресов,
 * создания кэша и нормализации правил в формате Adblock Plus.
 * Реализует механизм кэширования с автоматической проверкой актуальности
 * и обработку правил с удалением дубликатов и комментариев.
 *
 * @author russerver.com
 * @package HxAdblockUnifyingRules
 * @since 1.0.0
 */
class HxAdblockUnifyingRules
{
    /**
     * Имя основного файла кэша для хранения временных данных
     */
    private const PRIMARY_CACHE_FILE = 'temp.dat';

    /**
     * Имя вторичного файла кэша для хранения отсортированных данных
     */
    private const SORTED_CACHE_FILE = 'temp2.dat';

    /**
     * Хранит информацию о копирайтах и источниках фильтров
     * в формате "! URL\n" для последующего включения в итоговый файл правил
     *
     * @var string Строка с информацией об источниках
     */
    protected string $copyrights = '';

    /**
     * Хранит сообщение
     *
     * @var string
     */
    private string $message = '';

    /**
     * Конструктор класса
     *
     * Инициализирует объект и создает кэш при необходимости.
     * Загружает содержимое из указанных URL-адресов и сохраняет его в кэш-файл.
     * Выводит информацию о процессе загрузки для отладки.
     *
     * @param array $urls Массив URL-адресов для загрузки правил
     */
    public function __construct(array $urls)
    {
        if (!empty($urls)) {
            if (!$this->cache(self::PRIMARY_CACHE_FILE)) {
                $h1 = fopen(self::PRIMARY_CACHE_FILE, 'w');
                if ($h1) {
                    foreach ($urls as $currentUrl) {
                        $content = @file_get_contents($currentUrl);

                        if ($content !== false) {
                            $this->copyrights .= "! " . $currentUrl . "\n";
                            fwrite($h1, $content . "\n");
                        }

                        $this->message .= "<p>" . $currentUrl . "</p>\n";
                        sleep(1);
                    }

                    $this->message .= "<p>Файл кэша 1 обновлен.</p>\n";
                    fclose($h1);
                }
            } else {
                foreach ($urls as $currentUrl) {
                    $this->copyrights .= "! " . $currentUrl . "\n";
                }
            }
        }
    }

    /**
     * Проверяет актуальность кэш-файла
     *
     * Проверяет существование файла и его время модификации.
     * Файл считается актуальным, если он был изменен менее чем 12 часа назад.
     *
     * @param string $filename Имя проверяемого файла
     * @return bool true если кэш актуален, false если нужно обновление
     */
    private function cache(string $filename): bool
    {
        if (file_exists($filename)) {
            $i2 = filemtime($filename);
            if ((time() - 60 * 60 * 12) < $i2) {
                return true;
            }
        }

        return false;
    }

    /**
     * Возвращает текущее сообщение о статусе обработки
     *
     * Возвращает HTML-сформированное сообщение, которое содержит информацию
     * о выполненных операциях, таких как обновления кэша и создание файлов.
     *
     * @return string HTML-строка с сообщениями о статусе
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * Разделяет файл правил на несколько частей для совместимости с Adblock Plus в некоторых браузерах.
     *
     * Читает данные из кэша и разделяет их на три части.
     * Создает отдельные файлы для каждой части с добавлением необходимых заголовков.
     *
     * @param string $baseFileName Базовое имя файла для создаваемых частей
     * @return int 1 в случае успеха, 0 при ошибке
     */
    public function splitRulesFile(string $baseFileName): int
    {
        if (!$this->checkSplitFilesRelevance($baseFileName)) {
            return 0;
        }

        $arrayBuf = array();

        $h4 = fopen(self::SORTED_CACHE_FILE, "r");
        if ($h4) {
            while (!feof($h4)) {
                $arrayBuf[] = fgets($h4, 99999);
            }

            fclose($h4);
        }

        if (empty($arrayBuf)) {
            return 0;
        }

        $chunkSize = ceil(count($arrayBuf) / 3);
        $parts = array_chunk($arrayBuf, $chunkSize);

        for ($i = 0; $i < 3; $i++) {
            $fileName = sprintf("%s_%d.txt", $baseFileName, $i + 1);

            $h5 = fopen($fileName, "w");
            if ($h5) {
                fwrite($h5, "[Adblock Plus 2.0]\n");
                fwrite($h5, "! Version: " . date("j.n.Y") . "\n");
                fwrite($h5, "! Title: AdBlockPlus Part " . ($i + 1) . "/3\n");
                fwrite($h5, "!\n");
                fwrite($h5, $this->copyrights);
                fwrite($h5, "!\n");

                foreach ($parts[$i] as $rule) {
                    fwrite($h5, $rule);
                }

                fclose($h5);
                $this->message .= "<p>Создан файл: " . $fileName . "</p>\n";
            }
        }

        unset($arrayBuf);
        return 1;
    }

    /**
     * Проверяет актуальность всех разделённых частей файла правил
     *
     * Проверяет существование и время модификации всех трёх частей файла правил.
     * Файлы считаются актуальными, если они были изменены менее чем 12 часов назад.
     * Также проверяется наличие базового имени файла.
     *
     * @param string $baseFileName Базовое имя файла правил без номера части
     * @return bool true если все части актуальны, false при ошибке
     */
    private function checkSplitFilesRelevance(string $baseFileName): bool
    {
        $update = 0;

        if (empty($baseFileName)) {
            $this->message .= "<p>Внутренняя ошибка.</p>\n";
            return false;
        }

        if ($this->cache($baseFileName . '_1.txt')) {
            $this->message .= "<p>Файл: /" . $baseFileName . "_1.txt в актульном состоянии.</p>\n";
            $update = 1;
        }

        if ($this->cache($baseFileName . '_2.txt')) {
            $this->message .= "<p>Файл: /" . $baseFileName . "_2.txt в актульном состоянии.</p>\n";
            $update += 1;
        }

        if ($this->cache($baseFileName . '_3.txt')) {
            $this->message .= "<p>Файл: /" . $baseFileName . "_3.txt в актульном состоянии.</p>\n";
            $update += 1;
        }

        if ($update !== 3) {
            if ($this->normalization_cache()) {
                $this->message .= "<p>Кэш успешно обновлен.</p>\n";
                return true;
            } else {
                $this->message .= "<p>Ошибка: normalization_cache()</p>\n";
            }
        }

        return false;
    }

    /**
     * Нормализует кэшированные правила
     *
     * Читает данные из основного кэша, сортирует их и удаляет дубликаты,
     * пустые строки и комментарии. Сохраняет результат во вторичный кэш-файл.
     *
     * @return int 1 в случае успеха, 0 при ошибке
     */
    private function normalization_cache(): int
    {
        if ($this->cache(self::SORTED_CACHE_FILE)) {
            return 1;
        }

        $arrayBuf = array();
        $previousLine = '';

        $h2 = fopen(self::PRIMARY_CACHE_FILE, "r");
        if ($h2) {
            while (!feof($h2)) {
                $arrayBuf[] = fgets($h2, 99999);
            }

            sort($arrayBuf);
            fclose($h2);
        }

        if (empty($arrayBuf)) {
            return 0;
        }

        $h3 = fopen(self::SORTED_CACHE_FILE, "w");
        if ($h3) {
            foreach ($arrayBuf as $value) {
                $skip = 0;

                // Проверяем на пустую строку
                if (empty($value)) {
                    $skip = 1;
                }

                // Проверяем на слишком короткую строку
                if (strlen($value) < 2) {
                    $skip = 1;
                }

                // Проверяем на дубликат
                if ($value == $previousLine) {
                    $skip = 1;
                }

                // Пропускаем заголовки Adblock
                if (mb_strpos($value, "[Adblock") !== false) {
                    $skip = 1;
                }

                // Обрабатываем комментарии
                if (mb_strpos($value, "!") !== false) {
                    if (mb_strpos($value, "!") == 0) {
                        $skip = 1;
                    }
                }

                if (!$skip) {
                    fwrite($h3, $value);
                }

                $previousLine = $value;
            }

            unset($arrayBuf, $previousLine);
            fclose($h3);
            $this->message .= "<p>Файл кэша 2 обновлен.</p>\n";
            return 1;
        }

        return 0;
    }
}

/* Список правил для adblock */
$urls = ['https://easylist-downloads.adblockplus.org/ruadlist+easylist.txt',
		'https://easylist-downloads.adblockplus.org/i_dont_care_about_cookies.txt',
		'https://easylist-downloads.adblockplus.org/fanboy-social.txt',
		'https://easylist-downloads.adblockplus.org/fanboy-notifications.txt',
		'https://easylist-downloads.adblockplus.org/easyprivacy.txt',
		'https://filters.adtidy.org/extension/ublock/filters/22.txt',
		'https://ublockorigin.pages.dev/thirdparties/easylist.txt',
		'https://easylist-downloads.adblockplus.org/abp-filters-anti-cv.txt'];

$downloader = new HxAdblockUnifyingRules($urls);
$downloader->splitRulesFile('adblock');
echo $downloader->message();
