<?php
namespace Tanbolt\Console\Component;

use DOMNode;
use DOMDocument;
use Tanbolt\Console\Ansi;
use Tanbolt\Console\Helper;

class RichText extends AbstractStyle
{
    /**
     * @var array
     */
    protected $tags = [];

    /**
     * @var string
     */
    protected $lastText;

    /**
     * 增加一个可用标签，默认可使用 Ansi::$theme 中的标签
     * @param string $tag
     * @param array $style
     * @return $this
     * @see Ansi::$theme
     */
    public function addTag(string $tag, array $style = [])
    {
        $this->tags[$tag] = $style;
        return $this;
    }

    /**
     * 获取富文本的 ansi 文本
     * @param string $text
     * @param ?callable $output 解析回调，$output(string $message, array $style)，可自行根据回调处理解析结果，不指定直接返回
     * @return string
     */
    public function getRichText(string $text, callable $output = null)
    {
        return $this->makeRichText($text, $output ?: false);
    }

    /**
     * 输出富文本，可使用支持的标签
     * ```
     * -------------------------------------------------------------------------------
     * ########
     * 支持使用类似 HTML 标签的属性，用以设置样式，如
     * 中间一段 <text background="red" color="white" bold>红底白字加粗</text> 的字体
     *
     * color, background 颜色值仅支持8种色值
     * black / red / green / yellow / blue / magenta / cyan / white
     *
     * 其他属性支持
     * bold / dim / italic / underline / blinking / strikethrough / bright / bgBright
     *
     * ########
     * 另外可使用内置标签(参见 Ansi::$theme) 或 addTag() 设置的标签，这些标签自带样式，如：
     * <info>信息</info>
     * <comment>注释</comment>
     * <notice>提醒</notice>
     * <warn>警告</warn>
     * <error>错误</error>
     *
     * 同时，这些内置标签也支持属性，可通过属性覆盖原本样式，如:
     * <info bold color="blue">信息</info>
     *
     * ########
     * 支持标签嵌套，子级标签会继承父标签的样式，但子标签可重置样式，类似 CSS
     * <text background="red" color="white" bold>
     *    111111
     *    <text background="green" bold="0"> 2222 </text>
     * </text>
     * -------------------------------------------------------------------------------
     * ```
     * @param string $text 富文本
     * @param bool $canClear 是否可以清除，若输出后无清除需求，设置为 false 以免造成副作用
     * @return $this
     * @see Ansi::$theme
     * @see clear()
     */
    public function write(string $text, bool $canClear = false)
    {
        $this->makeRichText($text, $canClear ?: null);
        return $this;
    }

    /**
     * 获取或输出富文本的 ansi 文本
     * @param string $text
     * @param callable|bool|null $output
     * @return string
     */
    protected function makeRichText(string $text, $output = false)
    {
        $text = Helper::crlfToLf($text, true);
        if (true === $output) {
            $this->lastText = $text;
        } elseif (null === $output) {
            $output = true;
        }
        $text = preg_replace_callback('/<(\w+)([^>]*)>/', function ($matches) {
            $att = trim($matches[2]);
            $att = ($att ? ' ' : '').$att;
            return '<div tag="'.$matches[1].'"'.$att.'>';
        }, $text);
        $text = '<html lang=""><body>'
            .preg_replace('/<\/([^>]*)>/', '</div>', $text)
            .'</body></html>';
        $xml = new DOMDocument('1.0', 'utf-8');
        $xml->loadHTML($text, LIBXML_DTDLOAD|LIBXML_NOERROR|LIBXML_NOWARNING);
        return $this->makeNodeText(
            $xml->childNodes->item(0)->childNodes->item(0),
            Ansi::instance($this->output),
            $output
        );
    }

    /**
     * 解析富文本
     * @param DOMNode $node
     * @param Ansi $ansi
     * @param callable|bool|null $output
     * @param array $parentStyle
     * @return string
     */
    protected function makeNodeText(DOMNode $node, Ansi $ansi, $output = false, array $parentStyle = [])
    {
        $text = '';

        // 属性提取，设置样式
        $key = 0;
        $tag = null;
        $styles = $parentStyle;
        $attributes = $node->attributes;
        while ($key < $attributes->length) {
            $att = $attributes->item($key);
            if ('tag' === $att->nodeName) {
                $tag = $att->nodeValue ?: null;
            } else {
                $styles[$att->nodeName] = 'color' === $att->nodeName || 'background' === $att->nodeName
                    ? $att->nodeValue
                    : !('0' === $att->nodeValue);
            }
            $key++;
        }

        // 整理属性，默认使用 text 样式
        $tag = null === $tag ? 'text' : $tag;
        $defaultStyle = $this->tags[$tag] ?? Ansi::getTheme($tag);
        $defaultStyle = null === $defaultStyle ? Ansi::getTheme('text') : $defaultStyle;
        $styles = array_merge($defaultStyle, $styles);
        unset($styles['wrap']);

        // 输出/获取富文本
        $index = 0;
        $nodeList = $node->childNodes;
        $decorated = false === $output && $this->output->isStdoutDecorated();
        while ($index < $nodeList->length) {
            $child = $nodeList->item($index);
            if (XML_TEXT_NODE === $child->nodeType) {
                $value = utf8_decode($child->textContent);
                if (true === $output) {
                    $ansi->reset($styles)->stdout($value);
                } elseif (false === $output) {
                    $text .= $ansi->reset($styles)->getDecorated($value, $decorated);
                } else {
                    call_user_func($output, $value, $styles);
                }
            } else {
                $text .= $this->makeNodeText($child, $ansi, $output, $styles);
            }
            $index++;
        }
        return $text;
    }

    /**
     * 清除最后一次输出的可清除富文本
     * @param bool $adaptive 是否自适应窗口尺寸变化（默认使用上次获取到的窗口尺寸缓存）
     * @return $this
     */
    public function clear(bool $adaptive = false)
    {
        if ($this->lastText) {
            $this->terminal->revert(Helper::sectionLines(Helper::getPureText($this->lastText), $adaptive));
            $this->lastText = null;
        }
        return $this;
    }
}
