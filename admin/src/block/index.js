/**
 * The "Social login buttons" block. The server renders it; the editor shows
 * a preview with the label and where it sends people.
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

const P = 'nhrrob-social-login';

registerBlockType( 'nhrrob-social-login/buttons', {
	apiVersion: 3,
	title: __( 'Social login buttons', 'nhrrob-social-login' ),
	description: __(
		'"Continue with Google" and the other sign-in buttons, for visitors who are not logged in.',
		'nhrrob-social-login'
	),
	category: 'widgets',
	icon: 'admin-users',
	keywords: [ 'login', 'google', 'sign in' ],
	attributes: {
		label: { type: 'string', default: '' },
		redirect: { type: 'string', default: '' },
	},
	supports: { html: false },
	edit: function Edit( { attributes, setAttributes } ) {
		const label =
			attributes.label || __( 'Continue with', 'nhrrob-social-login' );

		return (
			<div { ...useBlockProps() }>
				<InspectorControls>
					<PanelBody title={ __( 'Buttons', 'nhrrob-social-login' ) }>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							label={ __( 'Label', 'nhrrob-social-login' ) }
							help={ __(
								'Comes before each provider\'s name. Empty: "Continue with".',
								'nhrrob-social-login'
							) }
							value={ attributes.label }
							onChange={ ( v ) => setAttributes( { label: v } ) }
						/>
						<TextControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							type="url"
							label={ __(
								'After signing in, go to',
								'nhrrob-social-login'
							) }
							help={ __(
								'Empty: back to this page.',
								'nhrrob-social-login'
							) }
							value={ attributes.redirect }
							onChange={ ( v ) =>
								setAttributes( { redirect: v } )
							}
						/>
					</PanelBody>
				</InspectorControls>
				<div className={ P }>
					<span className={ P + '__buttons' }>
						<span className={ P + '__button' }>
							<span>
								{ sprintf(
									/* translators: 1: the label, such as "Continue with". */
									__( '%1$s Google', 'nhrrob-social-login' ),
									label
								) }
							</span>
						</span>
					</span>
				</div>
				<p className={ P + '__note' }>
					{ __(
						'Visitors who are not logged in see a button for each provider that is on. Logged-in visitors see nothing.',
						'nhrrob-social-login'
					) }
				</p>
			</div>
		);
	},
	save: () => null,
} );
