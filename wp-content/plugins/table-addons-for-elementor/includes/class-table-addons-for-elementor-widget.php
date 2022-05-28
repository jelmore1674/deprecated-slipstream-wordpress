<?php
use \Elementor\Controls_Manager;
use \Elementor\Repeater;
use \Elementor\Group_Control_Border;
use \Elementor\Group_Control_Typography;
use \Elementor\Core\Schemes\Typography;


/**
 * Elementor Table Widget.
 *
 * @since 1.0.0
 */
class Table_Addons_For_Elementor_Widget extends \Elementor\Widget_Base {

	/**
	 * Get widget name.
	 *
	 * Retrieve oEmbed widget name.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string Widget name.
	 */
	public function get_name() {
		return 'Table';
	}

	/**
	 * Get widget title.
	 *
	 * Retrieve oEmbed widget title.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string Widget title.
	 */
	public function get_title() {
		return __( 'Table', 'plugin-name' );
	}

	/**
	 * Get widget icon.
	 *
	 * Retrieve oEmbed widget icon.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return string Widget icon.
	 */
	public function get_icon() {
		return 'eicon-table';
	}

	/**
	 * Get widget categories.
	 *
	 * Retrieve the list of categories the oEmbed widget belongs to.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @return array Widget categories.
	 */
	public function get_categories() {
		return [ 'general' ];
	}

	/**
	 * Register oEmbed widget controls.
	 *
	 * Adds different input fields to allow the user to change and customize the widget settings.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function _register_controls() {
		//Table Header start
		$this->start_controls_section(
			'content_table_header',
			[
				'label' => __( 'Table Header', 'table-addons-for-elementor' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$repeater_header = new Repeater();

		$repeater_header->add_control(
			'text', [
				'label' => __( 'Text', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::TEXT,
				'label_block' => true,
				'placeholder' => __( 'table data', 'table-addons-for-elementor' ),
				'default' => __( 'table data', 'table-addons-for-elementor' ),
				'dynamic' => [
		            'active' => true,
		        ]
			]
		);
		$repeater_header->add_control(
			'advance', [
				'label' => __( 'Advance Settings', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_off' => __( 'No', 'table-addons-for-elementor' ),
				'label_on' => __( 'Yes', 'table-addons-for-elementor' ),
			]
		);
		$repeater_header->add_control(
			'colspan', [
				'label' => __( 'colSpan', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'condition' => [
					'advance' => 'yes',
				],
				'label_off' => __( 'No', 'table-addons-for-elementor' ),
				'label_on' => __( 'Yes', 'table-addons-for-elementor' ),
			]
		);
		$repeater_header->add_control(
			'colspannumber', [
				'label' => __( 'colSpan Number', 'elementor' ),
				'type' => Controls_Manager::TEXT,
				'condition' => [
					'advance' => 'yes',
					'colspan' => 'yes',
				],
				'placeholder' => __( '1', 'table-addons-for-elementor' ),
				'default' => __( '1', 'table-addons-for-elementor' ),
			]
		);
		$repeater_header->add_control(
			'customwidth', [
				'label' => __( 'Custom Width', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'condition' => [
					'advance' => 'yes',
				],
				'label_off' => __( 'No', 'table-addons-for-elementor' ),
				'label_on' => __( 'Yes', 'table-addons-for-elementor' ),
			]
		);
		$repeater_header->add_responsive_control(
			'width', [
				'label' => __( 'Width', 'elementor' ),
				'type' => Controls_Manager::SLIDER,
				'condition' => [
					'advance' => 'yes',
					'customwidth' => 'yes',
				],
				'range' => [
					'%' => [
						'min' => 0,
						'max' => 100,
					],
					'px' => [
						'min' => 1,
						'max' => 1000,
					],
				],
				'devices' => [ 'desktop', 'tablet', 'mobile' ],
				'default' => [
					'size' => 30,
					'unit' => '%',
				],
				'size_units' => [ '%', 'px' ],
				'selectors' => [ '{{WRAPPER}} table.tafe-table {{CURRENT_ITEM}}' => 'width: {{SIZE}}{{UNIT}};',
				]
			]
		);
		$repeater_header->add_control(
			'align', [ 
				'label' => __( 'Alignment', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::CHOOSE,
				'condition' => [
					'advance' => 'yes',
				],
				'options' => [
					'left' => [
						'title' => __( 'Left', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-center',
					],
					'right' => [
						'title' => __( 'Right', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-right',
					],
					'justify' => [
						'title' => __( 'Justified', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-justify',
					],
				],
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} table.tafe-table {{CURRENT_ITEM}}' => 'text-align: {{VALUE}};',
				]
			]
		);
		$repeater_header->add_control(
			'decoration', [
				'label' => __( 'Decoration', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::SELECT,
				'condition' => [
					'advance' => 'yes',
				],
				'options' => [
					''  => __( 'Default', 'table-addons-for-elementor' ),
					'underline' => __( 'Underline', 'table-addons-for-elementor' ),
					'overline' => __( 'Overline', 'table-addons-for-elementor' ),
					'line-through' => __( 'Line Through', 'table-addons-for-elementor' ),
					'none' => __( 'None', 'table-addons-for-elementor' ),
				],
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} table.tafe-table {{CURRENT_ITEM}}' => 'text-decoration: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'table_header',
			[
				'label' => __( 'Table Header Cell', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::REPEATER,
				'fields' => $repeater_header->get_controls(),
				'default' => [
					[
						'text' => __( 'Table Header', 'table-addons-for-elementor' ),
					],
					[
						'text' => __( 'Table Header', 'table-addons-for-elementor' ),
					]
				],
				'title_field' => '{{{ text }}}',
			]
		);

		$this->end_controls_section();

		// Table Body Start
		$this->start_controls_section(
			'content_table_body',
			[
				'label' => __( 'Table Body', 'table-addons-for-elementor' ),
				'tab' => Controls_Manager::TAB_CONTENT,
			]
		);

		$repeater = new Repeater();

		//$repeater->start_controls_tabs( 'slides_repeater' );
		//$repeater->start_controls_tab( 'general_table_body', [ 'label' => __( 'General', 'elementor-pro' ) ] );

		$repeater->add_control(
			'row', [
				'label' => __( 'New Row', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_off' => __( 'No', 'table-addons-for-elementor' ),
				'label_on' => __( 'Yes', 'table-addons-for-elementor' ),
			]
		);

		$repeater->add_control(
			'text', [
				'label' => __( 'Text', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::TEXTAREA,
				'label_block' => true,
				'placeholder' => __( 'Table Data', 'table-addons-for-elementor' ),
				'default' => __( 'Table Data', 'table-addons-for-elementor' ),
				'dynamic' => [
		            'active' => true,
		        ]
			]
		);

		//$repeater->end_controls_tab();
		//$repeater->start_controls_tab( 'advance_table_body', [ 'label' => __( 'Advance', 'elementor-pro' ) ] );

		$repeater->add_control(
			'advance', [
				'label' => __( 'Advance Settings', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_off' => __( 'No', 'table-addons-for-elementor' ),
				'label_on' => __( 'Yes', 'table-addons-for-elementor' ),
			]
		);

		$repeater->add_control(
			'colspan', [
				'label' => __( 'colSpan', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'condition' => [
					'advance' => 'yes',
				],
				'label_off' => __( 'No', 'table-addons-for-elementor' ),
				'label_on' => __( 'Yes', 'table-addons-for-elementor' ),
			]
		);

		$repeater->add_control(
			'colspannumber', [
				'label' => __( 'colSpan Number', 'elementor' ),
				'type' => Controls_Manager::TEXT,
				'condition' => [
					'advance' => 'yes',
					'colspan' => 'yes',
				],
				'placeholder' => __( '1', 'table-addons-for-elementor' ),
				'default' => __( '1', 'table-addons-for-elementor' ),
			]
		);

		$repeater->add_control(
			'rowspan', [
				'label' => __( 'rowSpan', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'condition' => [
					'advance' => 'yes',
				],
				'label_off' => __( 'No', 'table-addons-for-elementor' ),
				'label_on' => __( 'Yes', 'table-addons-for-elementor' ),
			]
		);

		$repeater->add_control(
			'rowspannumber', [
				'label' => __( 'rowSpan Number', 'elementor' ),
				'type' => Controls_Manager::TEXT,
				'condition' => [
					'advance' => 'yes',
					'rowspan' => 'yes',
				],
				'placeholder' => __( '1', 'table-addons-for-elementor' ),
				'default' => __( '1', 'table-addons-for-elementor' ),
			]
		);

		$repeater->add_control(
			'align', [
				'label' => __( 'Alignment', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::CHOOSE,
				'condition' => [
					'advance' => 'yes',
				],
				'options' => [
					'left' => [
						'title' => __( 'Left', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-center',
					],
					'right' => [
						'title' => __( 'Right', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-right',
					],
					'justify' => [
						'title' => __( 'Justified', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-justify',
					],
				],
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} table.tafe-table {{CURRENT_ITEM}}' => 'text-align: {{VALUE}};',
				],
			]
		);

		$repeater->add_control(
			'decoration',
			[
				'label' => __( 'Decoration', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::SELECT,
				'condition' => [
					'advance' => 'yes',
				],
				'options' => [
					''  => __( 'Default', 'table-addons-for-elementor' ),
					'underline' => __( 'Underline', 'table-addons-for-elementor' ),
					'overline' => __( 'Overline', 'table-addons-for-elementor' ),
					'line-through' => __( 'Line Through', 'table-addons-for-elementor' ),
					'none' => __( 'None', 'table-addons-for-elementor' ),
				],
				'default' => '',
				'selectors' => [
					'{{WRAPPER}} table.tafe-table {{CURRENT_ITEM}}' => 'text-decoration: {{VALUE}};',
				],
			]
		);

		/*---
		$repeater->add_group_control(
			\Elementor\Group_Control_Typography::get_type(),
			[
				'name' => 'td_typography',
				'label' => __( 'Typography', 'table-addons-for-elementor' ),
				'selector' => '{{WRAPPER}} table.tafe-table {{CURRENT_ITEM}}',
				'scheme' => \Elementor\Core\Schemes\Typography::TYPOGRAPHY_4,
			]
		);
		----*/

		//$repeater->end_controls_tab();
		//$repeater->end_controls_tabs();


		$this->add_control(
			'table_body',
			[
				'label' => __( 'Table Body Cell', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::REPEATER,
				'fields' => $repeater->get_controls(),
				'default' => [
					[
						'text' => __( 'Table Data', 'table-addons-for-elementor' ),
					],
					[
						'text' => __( 'Table Data', 'table-addons-for-elementor' ),
					],
				],
				'title_field' => '{{{ text }}}',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style',
			[
				'label' => __( 'General Style', 'table-addons-for-elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_control(
			'table_padding',
			[
				'label' => __( 'Inner Cell Padding', 'plugin-domain' ),
				'type' => Controls_Manager::DIMENSIONS,
				'size_units' => [ 'px', '%', 'em' ],
				'selectors' => [
					'{{WRAPPER}} table.tafe-table td,{{WRAPPER}} table.tafe-table th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
				],
			]
		);

		$this->add_group_control(
			Group_Control_Border::get_type(),
			[
				'name' => 'table_border',
				'label' => __( 'Border', 'table-addons-for-elementor' ),
				'selector' => '{{WRAPPER}} table.tafe-table td,{{WRAPPER}} table.tafe-table th',
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'table_header_style',
			[
				'label' => __( 'Table Header Style', 'table-addons-for-elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'header_align',
			[
				'label' => __( 'Alignment', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => __( 'Left', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-center',
					],
					'right' => [
						'title' => __( 'Right', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-right',
					],
					'justify' => [
						'title' => __( 'Justified', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-justify',
					],
				],
				'selectors' => [
					'{{WRAPPER}} table.tafe-table .tafe-table-header' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'header_text_color',
			[
				'label' => __( 'Text Color', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} table.tafe-table .tafe-table-header' => 'color: {{VALUE}};',
				]
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'header_typography',
				'selector' => '{{WRAPPER}} table.tafe-table .tafe-table-header',
				'scheme' => Typography::TYPOGRAPHY_3,
			]
		);

		$this->add_control(
			'header_bg_color',
			[
				'label' => __( 'Background Color', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} table.tafe-table .tafe-table-header' => 'background-color: {{VALUE}};',
				]
			]
		);

		$this->end_controls_section();

		$this->start_controls_section(
			'table_body_style',
			[
				'label' => __( 'Table Body Style', 'table-addons-for-elementor' ),
				'tab' => Controls_Manager::TAB_STYLE,
			]
		);

		$this->add_responsive_control(
			'body_align',
			[
				'label' => __( 'Alignment', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::CHOOSE,
				'options' => [
					'left' => [
						'title' => __( 'Left', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-left',
					],
					'center' => [
						'title' => __( 'Center', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-center',
					],
					'right' => [
						'title' => __( 'Right', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-right',
					],
					'justify' => [
						'title' => __( 'Justified', 'table-addons-for-elementor' ),
						'icon' => 'fa fa-align-justify',
					],
				],
				'selectors' => [
					'{{WRAPPER}} table.tafe-table .tafe-table-body' => 'text-align: {{VALUE}};',
				],
			]
		);

		$this->add_control(
			'body_text_color',
			[
				'label' => __( 'Text Color', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} table.tafe-table .tafe-table-body' => 'color: {{VALUE}};',
				]
			]
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			[
				'name' => 'body_typography',
				'selector' => '{{WRAPPER}} table.tafe-table .tafe-table-body',
				'scheme' => Typography::TYPOGRAPHY_3,
			]
		);

		$this->add_control(
			'body_bg_color',
			[
				'label' => __( 'Background Color', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::COLOR,
				'selectors' => [
					'{{WRAPPER}} table.tafe-table .tafe-table-body' => 'background-color: {{VALUE}};',
				]
			]
		);

		$this->add_control(
			'striped_bg', 
			[
				'label' => __( 'Striped Background', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::SWITCHER,
				'label_off' => __( 'No', 'table-addons-for-elementor' ),
				'label_on' => __( 'Yes', 'table-addons-for-elementor' ),
			]
		);
		$this->add_control(
			'striped_bg_color', 
			[
				'label' => __( 'Secondary Background Color', 'table-addons-for-elementor' ),
				'type' => Controls_Manager::COLOR,
				'condition' => [
					'striped_bg' => 'yes',
				],
				'selectors' => [
					'{{WRAPPER}} table.tafe-table .tafe-table-body tr:nth-of-type(2n)' => 'background-color: {{VALUE}};',
				]
			]
		);

		$this->end_controls_section();

	}

	/**
	 * Render oEmbed widget output on the frontend.
	 *
	 * Written in PHP and used to generate the final HTML.
	 *
	 * @since 1.0.0
	 * @access protected
	 */
	protected function render() {

		$settings = $this->get_settings_for_display();
		?>
		<table class="tafe-table">
			<thead  class="tafe-table-header">
				<tr>
					<?php
					foreach ($settings['table_header'] as $index => $item) {
						$repeater_setting_key = $this->get_repeater_setting_key( 'text', 'table_header', $index );
						$this->add_inline_editing_attributes( $repeater_setting_key );

						$colspan = ($item['colspan'] == 'yes' && $item['advance'] == 'yes') ? 'colSpan="'.$item['colspannumber'].'"' : '';

						echo '<th class="elementor-inline-editing elementor-repeater-item-'.$item['_id'].'"  '.$colspan.' '.$this->get_render_attribute_string( $repeater_setting_key ).'>'.$item['text'].'</th>';
					}
					?>
				</tr>
			</thead>
			<tbody class="tafe-table-body">
				<tr>
					<?php
					foreach ($settings['table_body'] as $index => $item) {
						$table_body_key = $this->get_repeater_setting_key( 'text', 'table_body', $index );

						$this->add_render_attribute( $table_body_key, 'class', 'elementor-repeater-item-'.$item['_id'] );
						$this->add_inline_editing_attributes( $table_body_key );

						if($item['row'] == 'yes'){
							echo '</tr><tr>';
						}

						$colspan = ($item['colspan'] == 'yes' && $item['advance'] == 'yes') ? 'colSpan="'.$item['colspannumber'].'"' : '';

						$rowspan = ($item['rowspan'] == 'yes' & $item['advance'] == 'yes') ? 'rowSpan="'.$item['rowspannumber'].'"' : '';

						echo '<td '.$colspan.' '.$rowspan.' '.$this->get_render_attribute_string( $table_body_key ).' >'.$item['text'].'</td>';
					}
					?>
				</tr>
			</tbody>
		</table>
		
		<?php

	}

	protected function _content_template() {
		?>
		<table class="tafe-table">
			<thead class="tafe-table-header">
				<tr>
					<#
					if ( settings.table_header ) {
						_.each( settings.table_header, function( item, index ) {
							var iconTextKey = view.getRepeaterSettingKey( 'text', 'table_header', index );

							if( 'yes' === item.colspan && 'yes' === item.advance){
								colSpan = 'colSpan="'+item.colspannumber+'"';
							}else{
								colSpan = '';
							}
							
							view.addRenderAttribute( iconTextKey, 'class', 'elementor-repeater-item-'+item._id );
							view.addInlineEditingAttributes( iconTextKey );
							#>
							<th {{{colSpan}}} {{{ view.getRenderAttributeString( iconTextKey ) }}}>{{{ item.text }}}</th>
						<#
						} );
					} #>
				</tr>
			</thead>
			<tbody class="tafe-table-body">
				<tr>
					<#
					if ( settings.table_body ) {
						_.each( settings.table_body, function( item, index ) {
							if( 'yes' === item.row){
								newRow = '</tr><tr>';
							}else{
								newRow = '';
							}

							if( 'yes' === item.colspan && 'yes' === item.advance){
								colSpan = 'colSpan="'+item.colspannumber+'"';
							}else{
								colSpan = '';
							}

							if( 'yes' === item.rowspan && 'yes' === item.advance){
								rowSpan = 'rowSpan="'+item.rowspannumber+'"';
							}else{
								rowSpan = '';
							}

							var tdTextKey = view.getRepeaterSettingKey( 'text', 'table_body', index );
							
							view.addRenderAttribute( tdTextKey, 'class', 'elementor-repeater-item-'+item._id );
							view.addInlineEditingAttributes( tdTextKey );

							#>
							{{{newRow}}}
							<td {{{rowSpan}}} {{{colSpan}}} {{{ view.getRenderAttributeString( tdTextKey ) }}}>{{{ item.text }}}</td>
						<#
						} );
					} #>
				</tr>
			</tbody>
		</table>
		<?php
	}
}