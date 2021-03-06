<?php
/**
 * Utiity function to list hooks that are connected to actions / filters
 * Great for debugging the actions and filters
 * @param string $hook
 * @return array
 */
function ssc_list_hooks( $hook = '' ) {
    global $wp_filter;

    if ( isset( $wp_filter[$hook]->callbacks ) ) {
        array_walk( $wp_filter[$hook]->callbacks, function( $callbacks, $priority ) use ( &$hooks ) {
            foreach ( $callbacks as $id => $callback )
                $hooks[] = array_merge( array( 'id' => $id, 'priority' => $priority ), $callback );
        });
    } else {
        return array();
    }

    foreach( $hooks as &$item ) {
        // skip if callback does not exist
        if ( !is_callable( $item['function'] ) ) continue;

        // function name as string or static class method eg. 'Foo::Bar'
        if ( is_string( $item['function'] ) ) {
            $ref = strpos( $item['function'], '::' ) ? new \ReflectionClass( strstr( $item['function'], '::', true ) ) : new \ReflectionFunction( $item['function'] );
            $item['file'] = $ref->getFileName();
            $item['line'] = get_class( $ref ) == 'ReflectionFunction'
                ? $ref->getStartLine()
                : $ref->getMethod( substr( $item['function'], strpos( $item['function'], '::' ) + 2 ) )->getStartLine();

            // array( object, method ), array( string object, method ), array( string object, string 'parent::method' )
        } elseif ( is_array( $item['function'] ) ) {

            $ref = new \ReflectionClass( $item['function'][0] );

            // $item['function'][0] is a reference to existing object
            $item['function'] = array(
                is_object( $item['function'][0] ) ? get_class( $item['function'][0] ) : $item['function'][0],
                $item['function'][1]
            );
            $item['file'] = $ref->getFileName();
            $item['line'] = strpos( $item['function'][1], '::' )
                ? $ref->getParentClass()->getMethod( substr( $item['function'][1], strpos( $item['function'][1], '::' ) + 2 ) )->getStartLine()
                : $ref->getMethod( $item['function'][1] )->getStartLine();

            // closures
        } elseif ( is_callable( $item['function'] ) ) {
            $ref = new \ReflectionFunction( $item['function'] );
            $item['function'] = get_class( $item['function'] );
            $item['file'] = $ref->getFileName();
            $item['line'] = $ref->getStartLine();

        }
    }

    return $hooks;
}

/**
 * An utility function to remove any hook from a class
 * @param $tag
 * @param $class
 * @param $method
 * @return array|bool|void
 */
function ssc_remove_anonymous_object_filter( $tag, $class, $method )
{
    if (!isset($GLOBALS['wp_filter'][ $tag ]) || empty($GLOBALS['wp_filter'][ $tag ]))
        return;
    
    foreach ( $GLOBALS['wp_filter'][ $tag ] as $priority => $filter )
    {
        foreach ( $filter as $identifier => $function )
        {
            if ( is_array( $function)
                and is_a( $function['function'][0], $class )
                and $method === $function['function'][1]
            )
            {

                remove_filter(
                    $tag,
                    array ( $function['function'][0], $method ),
                    $priority
                );
                return array($tag,array ( $function['function'][0], $method ),$priority);
            }
        }
    }

    return false;
}

