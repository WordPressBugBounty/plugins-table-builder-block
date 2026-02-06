import { __ } from '@wordpress/i18n';
import {
	RichText,
	BlockIcon,
	useBlockProps,
	__experimentalGetElementClassName,
	useInnerBlocksProps,
} from '@wordpress/block-editor';
import { blockTable as icon } from '@wordpress/icons';
import { Button, Placeholder } from '@wordpress/components';

export default function Edit( {
	attributes,
	setAttributes,
	clientId,
	advancedControl,
} ) {
	const {
		caption,
		rowCount,
		columnCount,
		headers,
		footers,
		hasHeader,
		hasFooter,
		isEmpty,
	} = attributes;
	const { GkitStyle, GkitNumber, GkitSwitcher } = window.gutenkit.components;
	const { useDeviceType } = window.gutenkit.helpers;
	const device = useDeviceType();
	const template = Array.from( { length: rowCount }, () => [
		'gutenkit/table-builder-row',
	] );
	const blockProps = useBlockProps();
	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'gkit-table__body',
		},
		{
			allowedBlocks: [ 'gutenkit/table-builder-row' ],
			renderAppender: false,
			template,
		}
	);

	return (
		<>
			<figure { ...blockProps }>
				{ ! isEmpty ? (
					<>
						<table className="gkit-table">
							{ hasHeader && (
								<thead className="gkit-table__header">
									<tr>{ Headers }</tr>
								</thead>
							) }
							<tbody { ...innerBlocksProps }></tbody>
							{ hasFooter && (
								<tfoot className="gkit-table__footer">
									<tr>{ Footers }</tr>
								</tfoot>
							) }
						</table>
						<RichText
							identifier="caption"
							tagName="figcaption"
							className="gkit-table__caption"
							value={ caption }
							onChange={ ( value ) =>
								setAttributes( { caption: value } )
							}
							placeholder="Caption for Table"
						/>
					</>
				) : (
					<Placeholder
						label={ __( 'Table' ) }
						icon={ <BlockIcon icon={ icon } showColors /> }
						instructions={ __(
							'Insert a table for sharing data.'
						) }
					>
						<form
							className="blocks-table__placeholder-form"
							onSubmit={ () =>
								setAttributes( { isEmpty: false } )
							}
						>
						</form>
					</Placeholder>
				) }
			</figure>
		</>
	);
}
