import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const { columns, limit } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Badge Wall Settings', 'beer_slurper' ) }>
					<RangeControl
						label={ __( 'Columns', 'beer_slurper' ) }
						value={ columns }
						onChange={ ( value ) =>
							setAttributes( { columns: value } )
						}
						min={ 2 }
						max={ 8 }
					/>
					<RangeControl
						label={ __( 'Limit (0 = all)', 'beer_slurper' ) }
						value={ limit }
						onChange={ ( value ) =>
							setAttributes( { limit: value } )
						}
						min={ 0 }
						max={ 200 }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="beer-slurper/badge-wall"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
