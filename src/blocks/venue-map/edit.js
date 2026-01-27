import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, TextControl, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

export default function Edit( { attributes, setAttributes } ) {
	const { height, zoom } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Map Settings', 'beer_slurper' ) }>
					<TextControl
						label={ __( 'Height', 'beer_slurper' ) }
						value={ height }
						onChange={ ( value ) =>
							setAttributes( { height: value } )
						}
						help={ __( 'CSS height value (e.g., 400px, 50vh)', 'beer_slurper' ) }
					/>
					<RangeControl
						label={ __( 'Default Zoom', 'beer_slurper' ) }
						value={ zoom }
						onChange={ ( value ) =>
							setAttributes( { zoom: value } )
						}
						min={ 1 }
						max={ 18 }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<ServerSideRender
					block="beer-slurper/venue-map"
					attributes={ attributes }
				/>
			</div>
		</>
	);
}
