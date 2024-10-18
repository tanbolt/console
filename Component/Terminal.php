<?php
namespace Tanbolt\Console\Component;

/**
 * Class Terminal
 * ```
 * 1、光标操作函数，非常类似与 PHP stream 的操作
 *   - move*() 系列函数类似于 fseek()，move 之后屏幕仅显示 开头 到 光标之间的内容
 *   - 但这并不意味着 未显示内容 被删除，通过 move 将光标显示移动到后方，仍会显示之前的内容
 *   - 若在 move 后输出内容，则类似于 fseek() 之后 fwrite()，之前的内容将被覆盖
 *   - 如：stdout('1234')
 *        ->moveLeft(2)  #光标在'3'的位置,显示'12'
 *        ->stdout('A')  #'3'的位置被'A'覆盖,光标在'4'上但不显示'4'
 *        ->moveRight(1) #向右移动光标后'4'被显示
 *     显示：'12A4'
 *
 * 2、清除屏幕
 *   - clearRight() - 清除光标(包括光标位置内容)至屏幕结尾的内容
 *     stdout('1234')->moveLeft(2)->clearRight()->moveRight(2)
 *     显示：'12\s\s' (原内容'34'被清除)
 *   - clearLeft() - 清除光标(包括光标位置内容)至屏幕开始的内容
 *     stdout('1234')->moveLeft(2)->clearRight()->->moveRight(2)
 *     清除后光标的位置保持不变，假设终端窗口尺寸为 50*8，
 *     移动后光标在 '3' 的位置，首先清除当前输出行，然后再往上清除 7 行，最终为
 *     "\n\n.."(7个) + "\s\s\s4"(第八行内容) + 光标
 *
 * 3、清除行
 *   - clearLineRight()
 *   - clearLineLeft()
 *     与清除屏幕类似，只不过针对当前光标所在行，而不是整个屏幕
 *   - clearLine()
 *     清除光标所在的整行，即光标左右侧都被清除，清除后光标位置保持不变
 * ```
 * @package Tanbolt\Console\Component
 */
class Terminal extends AbstractStyle
{
    /**
     * 将光标 向上 移动 $rows 行
     * @param int $rows
     * @return $this
     */
    public function moveUp(int $rows = 1)
    {
        if ($rows < 1) {
            return $this;
        }
        return $this->outputCode($rows.'A');
    }

    /**
     * 将光标 向下 移动 $rows 行
     * @param int $rows
     * @return $this
     */
    public function moveDown(int $rows = 1)
    {
        if ($rows < 1) {
            return $this;
        }
        return $this->outputCode($rows.'B');
    }

    /**
     * 将光标 向右 移动 $columns 列
     * @param int $columns
     * @return $this
     */
    public function moveRight(int $columns = 1)
    {
        if ($columns < 1) {
            return $this;
        }
        return $this->outputCode($columns.'C');
    }

    /**
     * 将光标 向左 移动 $columns 列
     * @param int $columns
     * @return $this
     */
    public function moveLeft(int $columns = 1)
    {
        if ($columns < 1) {
            return $this;
        }
        return $this->outputCode($columns.'D');
    }

    /**
     * 将光标 向左 移动到【第 $column 列】，起始行为 1
     * @param int $column
     * @return $this
     */
    public function moveToColumn(int $column)
    {
        return $this->outputCode($column.'G');
    }

    /**
     * 将鼠标移动到指定行列，指定列好理解；
     * > 指定行与当前终端窗口尺寸相关，$row 是从当前窗口下所显示内容的第一行算起
     * @param int $row
     * @param int $column
     * @return $this
     */
    public function moveTo(int $row, int $column)
    {
        return $this->outputCode($row.';'.$column.'H');
    }

    /**
     * 清除光标所在行的内容
     * @return $this
     */
    public function clearLine()
    {
        return $this->outputCode('2K');
    }

    /**
     * 清除光标所在行的 光标右侧 内容
     * @return $this
     */
    public function clearLineRight()
    {
        return $this->outputCode('0K');
    }

    /**
     * 清除光标所在行的 光标左侧 内容
     * @return $this
     */
    public function clearLineLeft()
    {
        return $this->outputCode('1K');
    }

    /**
     * 从光标位置清除到屏幕末尾
     * @return $this
     */
    public function clearRight()
    {
        return $this->outputCode('0J');
    }

    /**
     * 从光标位置清除到屏幕开头
     * @return $this
     */
    public function clearLeft()
    {
        return $this->outputCode('1J');
    }

    /**
     * 清除整个屏幕
     * @return $this
     */
    public function clearScreen()
    {
        return $this->outputCode('2J');
    }

    /**
     * 删除 $rows 行已输出内容, 并光标置于行首
     * @param int $rows
     * @return Terminal
     */
    public function revert(int $rows)
    {
        return $this->moveToColumn(0)->moveUp($rows - 1)->clearRight();
    }

    /**
     * 隐藏光标
     * @return $this
     */
    public function hideCursor()
    {
        return $this->outputCode('?25l');
    }

    /**
     * 显示光标
     * @return $this
     */
    public function showCursor()
    {
        return $this->outputCode('?25h');
    }

    /**
     * 保存光标的当前位置
     * @return $this
     */
    public function saveCursorPosition()
    {
        return $this->outputCode('7', false);
    }

    /**
     * 恢复保存的光标位置
     * @return $this
     */
    public function restoreCursorPosition()
    {
        return $this->outputCode('8', false);
    }

    /**
     * 让终端发出 "嘟" 的提示音
     * @return $this
     */
    public function bell()
    {
        if ($this->output->isStdoutDecorated()) {
            $this->output->stdout("\x07");
        }
        return $this;
    }

    /**
     * 输出 CSI (Control Sequence Introducer) 控制符
     * @param string $code
     * @param bool $split
     * @return $this
     */
    private function outputCode(string $code, bool $split = true)
    {
        if ($this->output->isStdoutDecorated()) {
            $this->output->stdout("\x1b".($split ? '[' : '').$code);
        }
        return $this;
    }
}
