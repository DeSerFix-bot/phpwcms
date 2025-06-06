<?php

// SPDX-FileCopyrightText: 2004-2023 Ryan Parman, Sam Sneddon, Ryan McCue
// SPDX-License-Identifier: BSD-3-Clause

declare(strict_types=1);

namespace SimplePie;

use InvalidArgumentException;
use SimplePie\Cache\Base;
use SimplePie\Cache\BaseDataCache;
use SimplePie\Cache\CallableNameFilter;
use SimplePie\Cache\DataCache;
use SimplePie\Cache\NameFilter;
use SimplePie\Exception\HttpException;
use SimplePie\HTTP\Client;
use SimplePie\HTTP\FileClient;

/**
 * Used for data cleanup and post-processing
 *
 *
 * This class can be overloaded with {@see \SimplePie\SimplePie::set_sanitize_class()}
 *
 * @todo Move to using an actual HTML parser (this will allow tags to be properly stripped, and to switch between HTML and XHTML), this will also make it easier to shorten a string while preserving HTML tags
 */
class Sanitize implements RegistryAware
{
    // Private vars
    public $base;

    // Options
    public $remove_div = true;
    public $image_handler = '';
    public $strip_htmltags = ['base', 'blink', 'body', 'doctype', 'embed', 'font', 'form', 'frame', 'frameset', 'html', 'iframe', 'input', 'marquee', 'meta', 'noscript', 'object', 'param', 'script', 'style'];
    public $encode_instead_of_strip = false;
    public $strip_attributes = ['bgsound', 'expr', 'id', 'style', 'onclick', 'onerror', 'onfinish', 'onmouseover', 'onmouseout', 'onfocus', 'onblur', 'lowsrc', 'dynsrc'];
    public $rename_attributes = [];
    public $add_attributes = ['audio' => ['preload' => 'none'], 'iframe' => ['sandbox' => 'allow-scripts allow-same-origin'], 'video' => ['preload' => 'none']];
    public $strip_comments = false;
    public $output_encoding = 'UTF-8';
    public $enable_cache = true;
    public $cache_location = './cache';
    public $cache_name_function = 'md5';

    /**
     * @var NameFilter
     */
    private $cache_namefilter;
    public $timeout = 10;
    public $useragent = '';
    public $force_fsockopen = false;
    public $replace_url_attributes = null;
    /**
     * @var array<mixed> Custom curl options
     * @see SimplePie::set_curl_options()
     */
    private $curl_options = [];

    public $registry;

    /**
     * @var DataCache|null
     */
    private $cache = null;

    /**
     * @var int Cache duration (in seconds)
     */
    private $cache_duration = 3600;

    /**
     * List of domains for which to force HTTPS.
     * @see \SimplePie\Sanitize::set_https_domains()
     * Array is a tree split at DNS levels. Example:
     * array('biz' => true, 'com' => array('example' => true), 'net' => array('example' => array('www' => true)))
     */
    public $https_domains = [];

    /**
     * @var Client|null
     */
    private $http_client = null;

    public function __construct()
    {
        // Set defaults
        $this->set_url_replacements(null);
    }

    public function remove_div($enable = true)
    {
        $this->remove_div = (bool) $enable;
    }

    public function set_image_handler($page = false)
    {
        if ($page) {
            $this->image_handler = (string) $page;
        } else {
            $this->image_handler = false;
        }
    }

    public function set_registry(\SimplePie\Registry $registry)/* : void */
    {
        $this->registry = $registry;
    }

    public function pass_cache_data($enable_cache = true, $cache_location = './cache', $cache_name_function = 'md5', $cache_class = Cache::class, DataCache $cache = null)
    {
        if (isset($enable_cache)) {
            $this->enable_cache = (bool) $enable_cache;
        }

        if ($cache_location) {
            $this->cache_location = (string) $cache_location;
        }

        if (! is_string($cache_name_function) && ! is_object($cache_name_function) && ! $cache_name_function instanceof NameFilter) {
            throw new InvalidArgumentException(sprintf(
                '%s(): Argument #3 ($cache_name_function) must be of type %s',
                __METHOD__,
                NameFilter::class
            ), 1);
        }

        // BC: $cache_name_function could be a callable as string
        if (is_string($cache_name_function)) {
            // trigger_error(sprintf('Providing $cache_name_function as string in "%s()" is deprecated since SimplePie 1.8.0, provide as "%s" instead.', __METHOD__, NameFilter::class), \E_USER_DEPRECATED);
            $this->cache_name_function = (string) $cache_name_function;

            $cache_name_function = new CallableNameFilter($cache_name_function);
        }

        $this->cache_namefilter = $cache_name_function;

        if ($cache !== null) {
            $this->cache = $cache;
        }
    }

    public function set_http_client(Client $http_client): void
    {
        $this->http_client = $http_client;
    }

    /**
     * @deprecated since SimplePie 1.9.0, use \SimplePie\Sanitize::set_http_client() instead.
     */
    public function pass_file_data($file_class = File::class, $timeout = 10, $useragent = '', $force_fsockopen = false, array $curl_options = [])
    {
        // trigger_error(sprintf('SimplePie\Sanitize::pass_file_data() is deprecated since SimplePie 1.9.0, please use "SimplePie\Sanitize::set_http_client()" instead.'), \E_USER_DEPRECATED);
        if ($timeout) {
            $this->timeout = (string) $timeout;
        }

        if ($useragent) {
            $this->useragent = (string) $useragent;
        }

        if ($force_fsockopen) {
            $this->force_fsockopen = (string) $force_fsockopen;
        }

        $this->curl_options = $curl_options;
    }

    public function strip_htmltags($tags = ['base', 'blink', 'body', 'doctype', 'embed', 'font', 'form', 'frame', 'frameset', 'html', 'iframe', 'input', 'marquee', 'meta', 'noscript', 'object', 'param', 'script', 'style'])
    {
        if ($tags) {
            if (is_array($tags)) {
                $this->strip_htmltags = $tags;
            } else {
                $this->strip_htmltags = explode(',', $tags);
            }
        } else {
            $this->strip_htmltags = false;
        }
    }

    public function encode_instead_of_strip($encode = false)
    {
        $this->encode_instead_of_strip = (bool) $encode;
    }

    public function rename_attributes($attribs = [])
    {
        if ($attribs) {
            if (is_array($attribs)) {
                $this->rename_attributes = $attribs;
            } else {
                $this->rename_attributes = explode(',', $attribs);
            }
        } else {
            $this->rename_attributes = false;
        }
    }

    public function strip_attributes($attribs = ['bgsound', 'expr', 'id', 'style', 'onclick', 'onerror', 'onfinish', 'onmouseover', 'onmouseout', 'onfocus', 'onblur', 'lowsrc', 'dynsrc'])
    {
        if ($attribs) {
            if (is_array($attribs)) {
                $this->strip_attributes = $attribs;
            } else {
                $this->strip_attributes = explode(',', $attribs);
            }
        } else {
            $this->strip_attributes = false;
        }
    }

    public function add_attributes($attribs = ['audio' => ['preload' => 'none'], 'iframe' => ['sandbox' => 'allow-scripts allow-same-origin'], 'video' => ['preload' => 'none']])
    {
        if ($attribs) {
            if (is_array($attribs)) {
                $this->add_attributes = $attribs;
            } else {
                $this->add_attributes = explode(',', $attribs);
            }
        } else {
            $this->add_attributes = false;
        }
    }

    public function strip_comments($strip = false)
    {
        $this->strip_comments = (bool) $strip;
    }

    public function set_output_encoding($encoding = 'UTF-8')
    {
        $this->output_encoding = (string) $encoding;
    }

    /**
     * Set element/attribute key/value pairs of HTML attributes
     * containing URLs that need to be resolved relative to the feed
     *
     * Defaults to |a|@href, |area|@href, |audio|@src, |blockquote|@cite,
     * |del|@cite, |form|@action, |img|@longdesc, |img|@src, |input|@src,
     * |ins|@cite, |q|@cite, |source|@src, |video|@src
     *
     * @since 1.0
     * @param array|null $element_attribute Element/attribute key/value pairs, null for default
     */
    public function set_url_replacements(?array $element_attribute = null)
    {
        if ($element_attribute === null) {
            $element_attribute = [
                'a' => 'href',
                'area' => 'href',
                'audio' => 'src',
                'blockquote' => 'cite',
                'del' => 'cite',
                'form' => 'action',
                'img' => [
                    'longdesc',
                    'src'
                ],
                'input' => 'src',
                'ins' => 'cite',
                'q' => 'cite',
                'source' => 'src',
                'video' => [
                    'poster',
                    'src'
                ]
            ];
        }
        $this->replace_url_attributes = (array) $element_attribute;
    }

    /**
     * Set the list of domains for which to force HTTPS.
     * @see \SimplePie\Misc::https_url()
     * Example array('biz', 'example.com', 'example.org', 'www.example.net');
     */
    public function set_https_domains($domains)
    {
        $this->https_domains = [];
        foreach ($domains as $domain) {
            $domain = trim($domain, ". \t\n\r\0\x0B");
            $segments = array_reverse(explode('.', $domain));
            $node =& $this->https_domains;
            foreach ($segments as $segment) {//Build a tree
                if ($node === true) {
                    break;
                }
                if (!isset($node[$segment])) {
                    $node[$segment] = [];
                }
                $node =& $node[$segment];
            }
            $node = true;
        }
    }

    /**
     * Check if the domain is in the list of forced HTTPS.
     */
    protected function is_https_domain($domain)
    {
        $domain = trim($domain, '. ');
        $segments = array_reverse(explode('.', $domain));
        $node =& $this->https_domains;
        foreach ($segments as $segment) {//Explore the tree
            if (isset($node[$segment])) {
                $node =& $node[$segment];
            } else {
                break;
            }
        }
        return $node === true;
    }

    /**
     * Force HTTPS for selected Web sites.
     */
    public function https_url($url)
    {
        return (strtolower(substr($url, 0, 7)) === 'http://') &&
            $this->is_https_domain(parse_url($url, PHP_URL_HOST)) ?
            substr_replace($url, 's', 4, 0) : //Add the 's' to HTTPS
            $url;
    }

    public function sanitize($data, $type, $base = '')
    {
        $data = trim($data);
        if ($data !== '' || $type & \SimplePie\SimplePie::CONSTRUCT_IRI) {
            if ($type & \SimplePie\SimplePie::CONSTRUCT_MAYBE_HTML) {
                if (preg_match('/(&(#(x[0-9a-fA-F]+|[0-9]+)|[a-zA-Z0-9]+)|<\/[A-Za-z][^\x09\x0A\x0B\x0C\x0D\x20\x2F\x3E]*' . \SimplePie\SimplePie::PCRE_HTML_ATTRIBUTE . '>)/', $data)) {
                    $type |= \SimplePie\SimplePie::CONSTRUCT_HTML;
                } else {
                    $type |= \SimplePie\SimplePie::CONSTRUCT_TEXT;
                }
            }

            if ($type & \SimplePie\SimplePie::CONSTRUCT_BASE64) {
                $data = base64_decode($data);
            }

            if ($type & (\SimplePie\SimplePie::CONSTRUCT_HTML | \SimplePie\SimplePie::CONSTRUCT_XHTML)) {
                if (!class_exists('DOMDocument')) {
                    throw new \SimplePie\Exception('DOMDocument not found, unable to use sanitizer');
                }
                $document = new \DOMDocument();
                $document->encoding = 'UTF-8';

                $data = $this->preprocess($data, $type);

                set_error_handler([Misc::class, 'silence_errors']);
                $document->loadHTML($data);
                restore_error_handler();

                $xpath = new \DOMXPath($document);

                // Strip comments
                if ($this->strip_comments) {
                    $comments = $xpath->query('//comment()');

                    foreach ($comments as $comment) {
                        $comment->parentNode->removeChild($comment);
                    }
                }

                // Strip out HTML tags and attributes that might cause various security problems.
                // Based on recommendations by Mark Pilgrim at:
                // http://diveintomark.org/archives/2003/06/12/how_to_consume_rss_safely
                if ($this->strip_htmltags) {
                    foreach ($this->strip_htmltags as $tag) {
                        $this->strip_tag($tag, $document, $xpath, $type);
                    }
                }

                if ($this->rename_attributes) {
                    foreach ($this->rename_attributes as $attrib) {
                        $this->rename_attr($attrib, $xpath);
                    }
                }

                if ($this->strip_attributes) {
                    foreach ($this->strip_attributes as $attrib) {
                        $this->strip_attr($attrib, $xpath);
                    }
                }

                if ($this->add_attributes) {
                    foreach ($this->add_attributes as $tag => $valuePairs) {
                        $this->add_attr($tag, $valuePairs, $document);
                    }
                }

                // Replace relative URLs
                $this->base = $base;
                foreach ($this->replace_url_attributes as $element => $attributes) {
                    $this->replace_urls($document, $element, $attributes);
                }

                // If image handling (caching, etc.) is enabled, cache and rewrite all the image tags.
                if (isset($this->image_handler) && ((string) $this->image_handler) !== '' && $this->enable_cache) {
                    $images = $document->getElementsByTagName('img');

                    foreach ($images as $img) {
                        if ($img->hasAttribute('src')) {
                            $image_url = $this->cache_namefilter->filter($img->getAttribute('src'));
                            $cache = $this->get_cache($image_url);

                            if ($cache->get_data($image_url, false)) {
                                $img->setAttribute('src', $this->image_handler . $image_url);
                            } else {
                                try {
                                    $response = $this->get_http_client()->request(
                                        Client::METHOD_GET,
                                        $img->getAttribute('src'),
                                        ['X-FORWARDED-FOR' => $_SERVER['REMOTE_ADDR']]
                                    );
                                } catch (HttpException $th) {
                                    continue;
                                }

                                if (! preg_match('/^http(s)?:\/\//i', $response->get_requested_uri()) || ($response->get_status_code() === 200 || $response->get_status_code() > 206 && $response->get_status_code() < 300)) {
                                    if ($cache->set_data($image_url, ['headers' => $response->get_headers(), 'body' => $response->get_body_content()], $this->cache_duration)) {
                                        $img->setAttribute('src', $this->image_handler . $image_url);
                                    } else {
                                        trigger_error("$this->cache_location is not writable. Make sure you've set the correct relative or absolute path, and that the location is server-writable.", E_USER_WARNING);
                                    }
                                }
                            }
                        }
                    }
                }

                // Get content node
                $div = $document->getElementsByTagName('body')->item(0)->firstChild;
                // Finally, convert to a HTML string
                $data = trim($document->saveHTML($div));

                if ($this->remove_div) {
                    $data = preg_replace('/^<div' . \SimplePie\SimplePie::PCRE_XML_ATTRIBUTE . '>/', '', $data);
                    $data = preg_replace('/<\/div>$/', '', $data);
                } else {
                    $data = preg_replace('/^<div' . \SimplePie\SimplePie::PCRE_XML_ATTRIBUTE . '>/', '<div>', $data);
                }

                $data = str_replace('</source>', '', $data);
            }

            if ($type & \SimplePie\SimplePie::CONSTRUCT_IRI) {
                $absolute = $this->registry->call(Misc::class, 'absolutize_url', [$data, $base]);
                if ($absolute !== false) {
                    $data = $absolute;
                }
            }

            if ($type & (\SimplePie\SimplePie::CONSTRUCT_TEXT | \SimplePie\SimplePie::CONSTRUCT_IRI)) {
                $data = htmlspecialchars($data, ENT_COMPAT, 'UTF-8');
            }

            if ($this->output_encoding !== 'UTF-8') {
                $data = $this->registry->call(Misc::class, 'change_encoding', [$data, 'UTF-8', $this->output_encoding]);
            }
        }
        return $data;
    }

    protected function preprocess($html, $type)
    {
        $ret = '';
        $html = preg_replace('%</?(?:html|body)[^>]*?'.'>%is', '', $html);
        if ($type & ~\SimplePie\SimplePie::CONSTRUCT_XHTML) {
            // Atom XHTML constructs are wrapped with a div by default
            // Note: No protection if $html contains a stray </div>!
            $html = '<div>' . $html . '</div>';
            $ret .= '<!DOCTYPE html>';
            $content_type = 'text/html';
        } else {
            $ret .= '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">';
            $content_type = 'application/xhtml+xml';
        }

        $ret .= '<html><head>';
        $ret .= '<meta http-equiv="Content-Type" content="' . $content_type . '; charset=utf-8" />';
        $ret .= '</head><body>' . $html . '</body></html>';
        return $ret;
    }

    public function replace_urls($document, $tag, $attributes)
    {
        if (!is_array($attributes)) {
            $attributes = [$attributes];
        }

        if (!is_array($this->strip_htmltags) || !in_array($tag, $this->strip_htmltags)) {
            $elements = $document->getElementsByTagName($tag);
            foreach ($elements as $element) {
                foreach ($attributes as $attribute) {
                    if ($element->hasAttribute($attribute)) {
                        $value = $this->registry->call(Misc::class, 'absolutize_url', [$element->getAttribute($attribute), $this->base]);
                        if ($value !== false) {
                            $value = $this->https_url($value);
                            $element->setAttribute($attribute, $value);
                        }
                    }
                }
            }
        }
    }

    public function do_strip_htmltags($match)
    {
        if ($this->encode_instead_of_strip) {
            if (isset($match[4]) && !in_array(strtolower($match[1]), ['script', 'style'])) {
                $match[1] = htmlspecialchars($match[1], ENT_COMPAT, 'UTF-8');
                $match[2] = htmlspecialchars($match[2], ENT_COMPAT, 'UTF-8');
                return "&lt;$match[1]$match[2]&gt;$match[3]&lt;/$match[1]&gt;";
            } else {
                return htmlspecialchars($match[0], ENT_COMPAT, 'UTF-8');
            }
        } elseif (isset($match[4]) && !in_array(strtolower($match[1]), ['script', 'style'])) {
            return $match[4];
        } else {
            return '';
        }
    }

    protected function strip_tag($tag, $document, $xpath, $type)
    {
        $elements = $xpath->query('body//' . $tag);
        if ($this->encode_instead_of_strip) {
            foreach ($elements as $element) {
                $fragment = $document->createDocumentFragment();

                // For elements which aren't script or style, include the tag itself
                if (!in_array($tag, ['script', 'style'])) {
                    $text = '<' . $tag;
                    if ($element->hasAttributes()) {
                        $attrs = [];
                        foreach ($element->attributes as $name => $attr) {
                            $value = $attr->value;

                            // In XHTML, empty values should never exist, so we repeat the value
                            if (empty($value) && ($type & \SimplePie\SimplePie::CONSTRUCT_XHTML)) {
                                $value = $name;
                            }
                            // For HTML, empty is fine
                            elseif (empty($value) && ($type & \SimplePie\SimplePie::CONSTRUCT_HTML)) {
                                $attrs[] = $name;
                                continue;
                            }

                            // Standard attribute text
                            $attrs[] = $name . '="' . $attr->value . '"';
                        }
                        $text .= ' ' . implode(' ', $attrs);
                    }
                    $text .= '>';
                    $fragment->appendChild(new \DOMText($text));
                }

                $number = $element->childNodes->length;
                for ($i = $number; $i > 0; $i--) {
                    $child = $element->childNodes->item(0);
                    $fragment->appendChild($child);
                }

                if (!in_array($tag, ['script', 'style'])) {
                    $fragment->appendChild(new \DOMText('</' . $tag . '>'));
                }

                $element->parentNode->replaceChild($fragment, $element);
            }

            return;
        } elseif (in_array($tag, ['script', 'style'])) {
            foreach ($elements as $element) {
                $element->parentNode->removeChild($element);
            }

            return;
        } else {
            foreach ($elements as $element) {
                $fragment = $document->createDocumentFragment();
                $number = $element->childNodes->length;
                for ($i = $number; $i > 0; $i--) {
                    $child = $element->childNodes->item(0);
                    $fragment->appendChild($child);
                }

                $element->parentNode->replaceChild($fragment, $element);
            }
        }
    }

    protected function strip_attr($attrib, $xpath)
    {
        $elements = $xpath->query('//*[@' . $attrib . ']');

        foreach ($elements as $element) {
            $element->removeAttribute($attrib);
        }
    }

    protected function rename_attr($attrib, $xpath)
    {
        $elements = $xpath->query('//*[@' . $attrib . ']');

        foreach ($elements as $element) {
            $element->setAttribute('data-sanitized-' . $attrib, $element->getAttribute($attrib));
            $element->removeAttribute($attrib);
        }
    }

    protected function add_attr($tag, $valuePairs, $document)
    {
        $elements = $document->getElementsByTagName($tag);
        foreach ($elements as $element) {
            foreach ($valuePairs as $attrib => $value) {
                $element->setAttribute($attrib, $value);
            }
        }
    }

    /**
     * Get a DataCache
     *
     * @param string $image_url Only needed for BC, can be removed in SimplePie 2.0.0
     *
     * @return DataCache
     */
    private function get_cache(string $image_url = ''): DataCache
    {
        if ($this->cache === null) {
            // @trigger_error(sprintf('Not providing as PSR-16 cache implementation is deprecated since SimplePie 1.8.0, please use "SimplePie\SimplePie::set_cache()".'), \E_USER_DEPRECATED);
            $cache = $this->registry->call(Cache::class, 'get_handler', [
                $this->cache_location,
                $image_url,
                Base::TYPE_IMAGE
            ]);

            return new BaseDataCache($cache);
        }

        return $this->cache;
    }

    /**
     * Get a HTTP client
     */
    private function get_http_client(): Client
    {
        if ($this->http_client === null) {
            return new FileClient(
                $this->registry,
                [
                    'timeout' => $this->timeout,
                    'redirects' => 5,
                    'useragent' => $this->useragent,
                    'force_fsockopen' => $this->force_fsockopen,
                    'curl_options' => $this->curl_options,
                ]
            );
        }

        return $this->http_client;
    }
}

class_alias('SimplePie\Sanitize', 'SimplePie_Sanitize');
