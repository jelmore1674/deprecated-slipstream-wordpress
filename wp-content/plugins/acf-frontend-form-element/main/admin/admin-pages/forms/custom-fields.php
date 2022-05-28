<?php

if ( !defined( 'ABSPATH' ) ) {
    exit;
    // Exit if accessed directly
}


if ( function_exists( 'acf_add_local_field' ) ) {
    acf_add_local_field( array(
        'key'      => 'frontend_admin_title',
        'label'    => __( 'Title', FEA_NS ),
        'required' => true,
        'name'     => 'frontend_admin_title',
        'type'     => 'post_title',
    ) );
    acf_add_local_field( array(
        'key'      => 'frontend_admin_term_name',
        'label'    => __( 'Name', FEA_NS ),
        'required' => true,
        'name'     => 'frontend_admin_term_name',
        'type'     => 'term_name',
    ) );
    acf_add_local_field( array(
        'key'      => 'acf_frontend_custom_term',
        'label'    => __( 'Value', FEA_NS ),
        'required' => true,
        'name'     => 'acf_frontend_custom_term',
        'type'     => 'text',
    ) );
    $form_types = array(
        'general'            => __( FEA_TITLE . ' Form', FEA_NS ),
        __( 'Post', FEA_NS ) => array(
        'edit_post'      => __( 'Edit Post Form', FEA_NS ),
        'duplicate_post' => __( 'Duplicate Post Form', FEA_NS ),
        'new_post'       => __( 'New Post Form', FEA_NS ),
    ),
        __( 'User', FEA_NS ) => array(
        'edit_user' => __( 'Edit User Form', FEA_NS ),
        'new_user'  => __( 'New User Form', FEA_NS ),
    ),
        __( 'Term', FEA_NS ) => array(
        'edit_term' => __( 'Edit Term Form', FEA_NS ),
        'new_term'  => __( 'New Term Form', FEA_NS ),
    ),
    );
    acf_add_local_field( array(
        'label'      => __( 'Select Type', FEA_NS ),
        'name'       => 'admin_form_types',
        'key'        => 'admin_form_types',
        'required'   => true,
        'allow_null' => 1,
        'type'       => 'select',
        'choices'    => $form_types,
    ) );
}
