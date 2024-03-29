<?php

/**
 * Parser and renderer for ".cow" files used by the `cowsay` program.
 */
final class PhutilCowsay extends Phobject {

  private $template;
  private $eyes = 'oo';
  private $tongue = '  ';
  private $action = 'say';
  private $text;

  public function setTemplate($template) {
    $this->template = $template;
    return $this;
  }

  public function setEyes($eyes) {
    $this->eyes = $eyes;
    return $this;
  }

  public function setTongue($tongue) {
    $this->tongue = $tongue;
    return $this;
  }

  public function setAction($action) {
    $this->action = $action;
    return $this;
  }

  public function setText($text) {
    $this->text = $text;
    return $this;
  }

  public function renderCow() {
    $width = 40;
    $template = $this->template;

    // Real ".cow" files are Perl scripts which define a variable called
    // "$the_cow". We aren't going to interpret Perl, so just get everything
    // between the EOC (End Of Cow) tokens. The initial EOC might be in
    // quotes, and might have a semicolon.
    // We apply regexp modifiers
    // * 's' to make . match newlines within the EOC ... EOC block
    // * 'm' so we can use ^ to match start of line within the multiline string
    $matches = null;
    if (
      preg_match('/\$the_cow/', $template) &&
      preg_match('/EOC[\'"]?;?.*?^(.*?)^EOC/sm', $template, $matches)
    ) {
      $template = $matches[1];

      // Original .cow files are perl scripts which contain escaped sequences.
      // We attempt to unescape here by replacing any character preceded by a
      // backslash/escape with just that character.
      $template = preg_replace(
        '/\\\\(.)/',
        '$1',
        $template);
    } else {
      // Text template. Just strip away comments.
      $template = preg_replace('/^#.*$/', '', $template);
    }

    $token_patterns = array(
      '/\\$([a-z]+)/',
      '/\\${([a-z]+)}/',
    );
    foreach ($token_patterns as $token_pattern) {
      $template = preg_replace_callback(
        $token_pattern,
        array($this, 'replaceTemplateVariable'),
        $template);
      if ($template === false) {
        throw new Exception(
          pht('Failed to replace template variables while rendering cow!'));
      }
    }

    $lines = $this->text;

    // TODO: It would be nice to use a utf8 soft wrap here instead, but we
    // do not currently have one. Soft wrap first, then force to utf8.
    $lines = wordwrap($lines, $width - 4, "\n", true);
    $lines = phutil_split_lines($lines, false);
    foreach ($lines as $key => $line) {
      $lines[$key] = phutil_utf8ize($line);
    }

    if ($this->action == 'think') {
      $borders = '((()))';
    } else {
      if (count($lines) == 1) {
        $borders = '<<<>>>';
      } else {
        $borders = '/|\\\\|/';
      }
    }

    $size = 0;
    foreach ($lines as $line) {
      $size = max(strlen($line), $size);
    }

    $balloon = array();
    $balloon[] = ' '.str_repeat('_', $size + 2);
    $lines = array_values($lines);
    $last = (count($lines) - 1);
    foreach ($lines as $idx => $line) {
      if ($idx == 0) {
        $l = $borders[0];
        $r = $borders[3];
      } else if ($idx == $last) {
        $l = $borders[2];
        $r = $borders[5];
      } else {
        $l = $borders[1];
        $r = $borders[4];
      }
      $balloon[] = $l.' '.str_pad($line, $size).' '.$r;

    }
    $balloon[] = ' '.str_repeat('-', $size + 2);
    $balloon = implode("\n", $balloon);

    return rtrim($balloon."\n".$template);
  }

  public function replaceTemplateVariable($matches) {
    switch ($matches[1]) {
      case 'eyes':
        return str_pad($this->eyes, 2);
      case 'tongue':
        return str_pad($this->tongue, 2);
      case 'thoughts':
        return ($this->action == 'say' ? '\\' : 'o');
      default:
        return $matches[0];
    }
  }


}
