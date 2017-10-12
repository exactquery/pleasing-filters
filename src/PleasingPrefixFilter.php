<?php
/**
 * PleasingPrefixFilter.php
 */

namespace XQ\Pleasing\Filter;


use Assetic\Asset\AssetInterface;
use Assetic\Filter\FilterInterface;

/**
 * Pleasing and Assetic filter to prefix commonly used properties for usage in approximately the last four major browser
 * versions.
 *
 * This filter is simplistic in nature, and does not do fancy things like removing unneeded prefixes, looking up which
 * prefixes are needed, etc.
 *
 * @author  Aaron M Jones <am@jonesiscoding.com>
 * @version Pleasing Filters v1.0.10 (https://github.com/exactquery/pleasing-filters)
 * @license MIT (https://github.com/exactquery/pleasing-filters/blob/master/LICENSE)
 *
 * Class PleasingPrefixFilter
 * @package XQ\Pleasing\Filter
 */
class PleasingPrefixFilter implements FilterInterface
{
  /** @var array CSS Properties where the value is prefixed. */
  private $prefixValue = array(
      'display' => array(
          'flex'        => array( '-webkit-flex', '-ms-flexbox', 'flex' ),
          'inline-flex' => array( '-webkit-inline-box', '-ms-inline-flexbox', 'inline-flex' ),
      )
  );

  /** @var array CSS Properties where the property is prefixed. */
  private $prefixProperty = array(
      'flex-direction'        => array( '-webkit-flex-direction', '-ms-flex-direction', 'flex-direction' ),
      'flex-grow'             => array( '-webkit-flex-grow', '-ms-flex-positive', 'flex-grow' ),
      'flex-shrink'           => array( '-webkit-flex-shrink', '-ms-flex-negative', 'flex-shrink' ),
      'flex-basis'            => array( '-webkit-flex-basis', '-ms-flex-preferred-size', 'flex-basis' ),
      'flex-wrap'             => array( '-webkit-flex-wrap', '-ms-flex-wrap', 'flex-wrap' ),
      'order'                 => array( '-webkit-order', '-ms-flex-order', 'order' ),
      'transition'            => array( '-webkit-transition', '-o-transition', 'transition' ),
      'box-sizing'            => array( '-webkit-box-sizing', 'box-sizing' ),
      'column-count'          => array( '-webkit-column-count', 'column-count' ),
      'column-gap'            => array( '-webkit-column-gap', 'column-gap' ),
      'column-width'          => array( '-webkit-column-width', 'column-width' ),
      'column-rule'           => array( '-webkit-column-rule', 'column-rule' ),
      'user-select'           => array( '-webkit-user-select', '-moz-user-select', '-ms-user-select', 'user-select' ),
      'transform'             => array( '-webkit-transform', '-ms-transform', 'transform' ),
      'appearance'            => array( '-webkit-appearance', '-moz-appearance', 'appearance' ),
      'filter'                => array( '-webkit-filter', 'filter' ),
      'grid-template-columns' => array( '-ms-grid-columns', 'grid-template-columns' ),
      'grid-template-rows'    => array( '-ms-grid-rows', 'grid-template-rows' ),
      'grid-row-start'        => array( '-ms-grid-row', 'grid-row-start' ),
      'grid-column-start'     => array( '-ms-grid-column', 'grid-column-start' ),
      'justify-self'          => array( '-ms-grid-row-align', 'justify-self' )
  );

  /** @var array CSS properties where a custom method is used to properly prefix. */
  private $prefixMethod = array(
      'flex'            => 'prefixFlex',
      'justify-content' => 'prefixJustifyContent',
      'align-items'     => 'prefixAlignItems',
      'align-content'   => 'prefixAlignContent',
      'align-self'      => 'prefixAlignSelf'
  );


  // region //////////////////////////////////////////////// Filter Interface Methods

  /**
   * {@inheritdoc}
   */
  public function filterDump( AssetInterface $asset )
  {
  }

  /**
   * {@inheritdoc}
   */
  public function filterLoad( AssetInterface $asset )
  {
    $assetPath = $asset->getSourcePath();
    $ext       = ( $assetPath ) ? pathinfo( $assetPath, PATHINFO_EXTENSION ) : false;

    $content = $asset->getContent();

    switch( $ext )
    {
      case "less":
      case "scss":
      case "css":
        $output = $this->prefixCss( $content );
        break;
      default:
        // Extension not recognized, or could not be read.  Leave content alone
        $output = $content;
        break;
    }

    $asset->setContent( $output );
  }

  // endregion ///////////////////////////////////////////// End Filter Interface Methods

  // region //////////////////////////////////////////////// Main Method

  /**
   * Finds individual CSS rules and evaluates them for needed prefixes, then replaces the rules with the prefixed rule
   * as needed.
   *
   * @param string $content   The CSS, without prefixes.
   *
   * @return string           The CSS with prefixes.
   */
  protected function prefixCss( $content )
  {
    $replaced = array();
    if( $rules = $this->getRules( $content ) )
    {
      foreach( $rules as $rule )
      {
        $prefixed = array();
        if( strpos( $content, $rule->getRaw() ) !== false && !in_array( $rule->getRaw(), $replaced ) )
        {

          if( array_key_exists( $rule->getProperty(), $this->prefixValue ) )
          {
            if( array_key_exists( $rule->getValue(), $this->prefixValue[ $rule->getProperty() ] ) )
            {
              $prefixed = $this->getPrefixRules( $rule->getProperty(), $this->prefixValue[ $rule->getProperty() ][ $rule->getValue() ], $rule->getBang() );
            }
          }
          elseif( array_key_exists( $rule->getProperty(), $this->prefixProperty ) )
          {
            $prefixed = $this->getPrefixRules( $this->prefixProperty[ $rule->getProperty() ], $rule->getValue(), $rule->getBang() );
          }
          elseif( array_key_exists( $rule->getProperty(), $this->prefixMethod ) )
          {
            $method = $this->prefixMethod[ $rule->getProperty() ];
            $prefixed  = $this->$method( $rule->getValue(), $rule->getBang() );
          }

          if( !empty( $prefixed ) )
          {
            $newRules = array();
            foreach( $prefixed as $pRule )
            {
              /** @var CssRule $pRule */
              $pRule->setTemplate( $rule->getTemplate() );
              $newRules[] = $pRule->getOutput();
            }

            $replaced[] = $rule->getRaw();
            $replacement = str_replace( $rule->getOutput(), implode( "\n", $newRules ), $rule->getRaw() );
            $content = str_replace($rule->getRaw(),$replacement,$content);

          }
        }
      }
    }

    return $content;
  }

  /**
   * @param $content
   *
   * @return CssRule[]
   */
  protected function getRules( $content )
  {
    $rules = array();
    if( preg_match_all( '#{((?:[^{}]++|(?R))*+)}#', $content, $groups, PREG_SET_ORDER ) )
    {
      foreach( $groups as $group )
      {
        if( strpos( $group[ 1 ], '{' ) === false )
        {
          $set = preg_split( '#(\r|\n)#', $group[ 1 ] );

          foreach( $set as $s )
          {
            if( !empty($s) && $rule = CssRule::fromString( $s ) )
            {
              $rules[] = $rule;
            }
          }
        }
        else
        {
          $rules = array_merge( $rules, $this->getRules( $group[ 1 ] ) );
        }
      }
    }

    return (!empty($rules)) ? $rules : null;
  }

  // endregion ///////////////////////////////////////////// End Main Method

  // region //////////////////////////////////////////////// Prefix Methods

  /**
   * Properly prefixes the 'align-items' property.
   *
   * @param string  $value  The value of the property.
   * @param null    $extra  !important or null
   *
   * @return array          The prefixed rules to replace the 'align-items' rule.
   */
  protected function prefixAlignItems( $value, $extra = null )
  {
    $prop[] = '-ms-flex-align';

    switch( $value )
    {
      case 'flex-start':
        $val[] = 'start';
        break;
      case 'flex-end':
        $val[] = 'end';
        break;
      default:
        $val[]  = $value;
        $prop[] = '-ms-grid-row-align';
        $val[]  = 'center';
        break;
    }

    $prop[] = '-webkit-align-items';
    $prop[] = 'align-items';
    $val[]  = $value;
    $val[]  = $value;

    return $this->getPrefixRules( $prop, $val, $extra );
  }

  /**
   * Properly prefixes the 'align-content' property.
   *
   * @param string  $value  The value of the property.
   * @param null    $extra  !important or null
   *
   * @return array          The prefixed rules to replace the 'align-content' rule.
   */
  protected function prefixAlignContent( $value, $extra = null )
  {
    $prop = array(
        '-webkit-align-content',
        '-ms-flex-line-pack',
        'align-content'
    );

    $val[] = $value;
    switch( $value )
    {
      case 'flex-start':
        $val[] = 'start';
        break;
      case 'flex-end':
        $val[] = 'end';
        break;
      case 'space-between':
        $val[] = 'justify';
        break;
      case 'space-around':
        $val[] = 'distribute';
        break;
      default:
        $val[] = $value;
        break;
    }

    $val[] = $value;

    return $this->getPrefixRules( $prop, $val, $extra );
  }

  /**
   * Properly prefixes the 'align-self' property.
   *
   * @param string  $value  The value of the property.
   * @param null    $extra  !important or null
   *
   * @return array          The prefixed rules to replace the 'align-self' rule.
   */
  protected function prefixAlignSelf( $value, $extra = null )
  {
    $prop = array(
        '-webkit-align-self',
        '-ms-flex-item-align',
        'align-self'
    );

    $val[] = $value;
    switch( $value )
    {
      case 'flex-start':
        $val[] = 'start';
        break;
      case 'flex-end':
        $val[] = 'end';
        break;
      default:
        $val[] = $value;
        break;
    }

    $val[] = $value;

    return $this->getPrefixRules( $prop, $val, $extra );
  }

  /**
   * Properly prefixes the 'flex' property & value.
   *
   * @param string  $value  The value of the property.
   * @param null    $extra  !important or null
   *
   * @return array          The prefixed rules to replace the 'flex' rule.
   */
  protected function prefixFlex( $value, $extra = null )
  {
    $parts = explode( " ", $value );
    if( count( $parts ) == 3 )
    {
      // Make sure there's a % after the basis to avoid IE10/11 Bugs.
      // 0px does not work because a minifier would remove it.
      //
      // https://github.com/philipwalton/flexbugs#4-flex-shorthand-declarations-with-unitless-flex-basis-values-are-ignored
      if( $parts[ 2 ] === 0 || $parts[ 2 ] === '0px' )
      {
        $parts[ 2 ] = '0%';
      }
    }

    $prop = array( '-webkit-flex', '-ms-flex', 'flex' );
    $val  = implode( ' ', $parts );

    return $this->getPrefixRules( $prop, $val, $extra );
  }

  /**
   * Properly prefixes the 'justify-content' property.
   *
   * @param string  $value  The value of the property.
   * @param null    $extra  !important or null
   *
   * @return array          The prefixed rules to replace the 'justify-content' rule.
   */
  protected function prefixJustifyContent( $value, $extra = null )
  {
    $prop = array(
        '-webkit-justify-content',
        '-ms-flex-pack',
        'justify-content'
    );

    $val[] = $value;
    switch( $value )
    {
      case 'flex-start':
        $val[] = 'start';
        break;
      case 'flex-end':
        $val[] = 'end';
        break;
      case 'space-between':
        $val[] = 'justify';
        break;
      case 'space-around':
        $val[] = 'distribute';
        break;
      default:
        $val[] = $value;
    }
    $val[] = $value;

    return $this->getPrefixRules( $prop, $val, $extra );
  }

  // region //////////////////////////////////////////////// Private Helper Methods

  /**
   * Interpets the given parameters into a set of CSS rules to insert in replacement of the rule being evaluated.
   *
   * The number of properties and values given will be matched up to the other.  For instance, if one property
   * is given, it will be duplicated to match the count of the number of values given and vice versa.
   *
   * @param array|string         $prop    The property or properties to use with the rules.
   * @param array|string|float   $val     The value or values to use with the rules.
   * @param string               $extra   Any extra additions to the rules, such as !important
   *
   * @return array                        The CSS rules to insert
   */
  private function getPrefixRules( $prop, $val, $extra = null )
  {
    $extra = ( empty( $extra ) ) ? null : ' ' . $extra;
    if( !is_array( $prop ) && is_array( $val ) )
    {
      $properties = array_fill( 0, count( $val ), $prop );
      $values     = $val;
    }
    elseif( !is_array( $val ) && is_array( $prop ) )
    {
      $values     = array_fill( 0, count( $prop ), $val );
      $properties = $prop;
    }
    else
    {
      $properties = $prop;
      $values     = $val;
    }

    $property = null;
    $value    = null;
    do
    {
      $property = ( !empty( $properties ) ) ? array_shift( $properties ) : $property;
      $value    = ( !empty( $values ) ) ? array_shift( $values ) : $property;

      $rules[] = new CssRule( $property, $value, $extra );
    }
    while( !empty( $properties ) && !empty( $values ) );

    return $rules;
  }

  // endregion ///////////////////////////////////////////// End Private Helper Methods
}