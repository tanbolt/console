<?php
namespace Tanbolt\Console\Component;

use Tanbolt\Console\Ansi;
use Tanbolt\Console\Helper;
use Tanbolt\Console\Exception\RuntimeException;

class Table extends AbstractStyle
{
    const STYLE_CAPTION = [
        'color' => Ansi::COLOR_BLUE,
        'bold' => true,
    ];
    const STYLE_FIELD = [
        'color' => Ansi::COLOR_GREEN
    ];
    const STYLE_CELL = [];
    const STYLE_FOOTER = [];
    const STYLE_LINE = [];

    private static $outUnit = '═';
    private static $outSplit = '║';
    private static $outCornerF = '╔';
    private static $outCornerR = '╗';
    private static $outCornerFB = '╚';
    private static $outCornerRB = '╝';

    private static $corner = '+';
    private static $unit = '-';
    private static $split = '|';

    private $captionStyle = self::STYLE_CAPTION;
    private $fieldStyle = self::STYLE_FIELD;
    private $cellStyle = self::STYLE_CELL;
    private $footerStyle = self::STYLE_FOOTER;
    private $lineStyle = self::STYLE_LINE;

    private $doubleOuter = true;
    private $splitEveryRow = false;

    /**
     * 设置表格标题样式，null 则使用缺省样式
     * @param array|null $style
     * @return $this
     */
    public function captionStyle(?array $style)
    {
        $this->captionStyle = null === $style ? self::STYLE_CAPTION : $style;
        return $this;
    }

    /**
     * 设置表格字段名样式，null 则使用缺省样式
     * @param array|null $style
     * @return $this
     */
    public function fieldStyle(?array $style)
    {
        $this->fieldStyle = null === $style ? self::STYLE_FIELD : $style;
        return $this;
    }

    /**
     * 设置表格单元格样式，null 则使用缺省样式
     * @param array|null $style
     * @return $this
     */
    public function cellStyle(?array $style)
    {
        $this->cellStyle = null === $style ? self::STYLE_CELL : $style;
        return $this;
    }

    /**
     * 设置表格单元格样式，null 则使用缺省样式
     * @param array|null $style
     * @return $this
     */
    public function footerStyle(?array $style)
    {
        $this->footerStyle = null === $style ? self::STYLE_FOOTER : $style;
        return $this;
    }

    /**
     * 设置表格边线的样式
     * @param ?array $style
     * @return $this
     */
    public function lineStyle(?array $style)
    {
        $this->lineStyle = null === $style ? self::STYLE_LINE : $style;
        return $this;
    }

    /**
     * 外边框是否使用双划线
     * @param bool $double
     * @return $this
     */
    public function doubleOuterLine(bool $double = true)
    {
        $this->doubleOuter = $double;
        return $this;
    }

    /**
     * 在每一行下面都添加分割线
     * @param bool $split
     * @return $this
     */
    public function splitEveryRow(bool $split = true)
    {
        $this->splitEveryRow = $split;
        return $this;
    }

    /**
     * 输入一个表格
     * ```
     * $data，是一个数组，如：
     *    [
     *      [
     *          'name' => 'foo',
     *          'age' => 12,
     *          'weight' => 95
     *      ]
     *      [
     *          'name' => 'bar',
     *          'age' => 18,
     *          'weight' => 98
     *      ]
     *      [
     *          'name' => 'biz',
     *          'age' => 16
     *      ]
     *   ]
     *
     * 将使用数组中第一个元素的键值作为表格的基准, 输出
     *   +------+-----+--------+
     *   | name | age | weight |
     *   +------+-----+--------+
     *   | foo  | 12  | 95     |
     *   | bar  | 18  | 98     |
     *   | biz  | 16  |        |
     *   +------+-----+--------+
     * ```
     *
     * @param array $data 表格数据
     * @param ?string $caption 表格标题
     * @param ?string $footer 表格页脚
     * @return $this
     */
    public function write(array $data, string $caption = null, string $footer = null)
    {
        if (!count($data)) {
            return $this;
        }
        $first = reset($data);
        if (!is_array($first)) {
            throw new RuntimeException('Table data must be 2-dimensional array');
        }
        // 准备数据, $width 记录各字段宽度; $cell 记录每一行各单元格文字内容/文字长度
        $width = [];
        $cells = [];
        $first = array_keys($first);
        $titles = static::getCell(array_combine($first, $first), $first, $width);
        foreach ($data as $item) {
            $cells[] = static::getCell($item, $first, $width);
        }
        $double = $this->doubleOuter;
        $ansi = Ansi::instance($this->output);
        $line = static::getHorizontalLine($width, $double);
        $tableWidth = array_sum($width) + count($width) * 3 + 1;

        // 顶部横边线
        $ansi->reset($this->lineStyle)->wrap()->stdout(
            static::getHorizontalLine($width, $double, false)
        );
        // 标题
        if ($caption) {
            $this->writeCaption($ansi, $tableWidth, $caption);
            $ansi->reset($this->lineStyle)->wrap()->stdout($line);
        }
        // 表头
        foreach ($titles as $title) {
            $this->writeCell($ansi, $width, $title, true);
        }
        // 表格
        $first = true;
        foreach ($cells as $cell) {
            if ($first || $this->splitEveryRow) {
                $first = false;
                $ansi->reset($this->lineStyle)->wrap()->stdout($line);
            }
            foreach ($cell as $c) {
                $this->writeCell($ansi, $width, $c);
            }
        }
        // 页脚
        if ($footer) {
            $ansi->reset($this->lineStyle)->wrap()->stdout($line);
            $this->writeCaption($ansi, $tableWidth, $footer, true);
        }
        // 底部横边线
        $ansi->reset($this->lineStyle)->wrap()->stdout(
            static::getHorizontalLine($width, $double, true)
        );
        return $this;
    }

    /**
     * 输出标题
     * @param Ansi $ansi
     * @param int $tableWidth
     * @param string $caption
     * @param bool $footer
     */
    private function writeCaption(Ansi $ansi, int $tableWidth, string $caption, bool $footer = false)
    {
        $double = $this->doubleOuter;
        $caption = explode(Ansi::EOL, Helper::getChapter($caption, $tableWidth - 4, ' ', 'center'));
        foreach ($caption as $text) {
            $ansi->reset($this->lineStyle)->stdout($double ? self::$outSplit : self::$split)
                ->reset($footer ? $this->footerStyle : $this->captionStyle)->stdout(' '.$text.' ')
                ->reset($this->lineStyle)->wrap()->stdout($double ? self::$outSplit : self::$split);
        }
    }

    /**
     * 输出单元格
     * @param Ansi $ansi
     * @param array $width
     * @param array $cell
     * @param bool $filed
     */
    private function writeCell(Ansi $ansi, array $width, array $cell, bool $filed = false)
    {
        $first = true;
        $split = $this->doubleOuter ? self::$outSplit : self::$split;
        $ansi->reset($this->lineStyle)->stdout($split);
        foreach ($width as $key => $w) {
            if ($first) {
                $first = false;
            } else {
                $ansi->reset($this->lineStyle)->stdout(self::$split);
            }
            $text = isset($cell[$key])
                ? $cell[$key][0].static::repeat(' ', $w - $cell[$key][1])
                : static::repeat(' ', $w);
            $ansi->reset($filed ? $this->fieldStyle : $this->cellStyle)->stdout(' '.$text.' ');
        }
        $ansi->reset($this->lineStyle)->wrap()->stdout($split);
    }

    /**
     * 获取所有 cells 内容，宽度
     * @param array $data
     * @param array $keys
     * @param $width
     * @return array
     */
    private static function getCell(array $data, array $keys, &$width)
    {
        $segment = [];
        $maxCount = 0;
        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $cell = explode("\n", Helper::crlfToLf($data[$key]));
                $maxCount = max($maxCount, count($cell));
                $segment[$key] = $cell;
            }
        }
        $k = 0;
        $cells = [];
        while ($k < $maxCount) {
            $arr = [];
            foreach ($keys as $key) {
                if (isset($segment[$key][$k])) {
                    $str = $segment[$key][$k];
                    $w = Helper::strWidth($str);
                    $width[$key] = isset($width[$key]) ? max($width[$key], $w) : $w;
                    $arr[$key] = [$str, $w];
                }
            }
            if (count($arr)) {
                $cells[] = $arr;
            }
            $k++;
        }
        return $cells;
    }

    /**
     * 表格 横线
     * @param array $width
     * @param bool $double
     * @param bool|null $bottom
     * @return string
     */
    private static function getHorizontalLine(array $width, bool $double = false, bool $bottom = null)
    {
        // 表格 顶/底 部边框
        if (null !== $bottom) {
            $lineWidth = array_sum($width) + count($width) * 3 + 1 - 2;
            if (!$double) {
                return self::$corner.static::repeat(self::$unit, $lineWidth).self::$corner;
            }
            return ($bottom ? self::$outCornerFB : self::$outCornerF)
                .static::repeat(self::$outUnit, $lineWidth)
                .($bottom ? self::$outCornerRB : self::$outCornerR);
        }

        // 表格分割线
        $line = [];
        foreach ($width as $w) {
            $line[] = static::repeat(self::$unit, $w + 2);
        }
        $line = join(self::$corner, $line);

        // 双划线边框, 两头使用双划线
        if ($double) {
            return self::$outSplit.$line.self::$outSplit;
        }
        return self::$corner.$line.self::$corner;
    }

    /**
     * @param string $str
     * @param int $number
     * @return string
     */
    private static function repeat(string $str, int $number)
    {
        if ($number < 1) {
            return '';
        }
        return str_repeat($str, $number);
    }
}
