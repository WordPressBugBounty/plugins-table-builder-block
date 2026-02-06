import {
	useBlockProps,
	useInnerBlocksProps,
	RichText,
} from '@wordpress/block-editor';

export default function save( { attributes } ) {
	const { caption, headers, footers, hasHeader, hasFooter, isEmpty } =
		attributes;
	const blockProps = useBlockProps.save();
	const innerBlocksProps = useInnerBlocksProps.save( {
		className: 'gkit-table__body',
	} );

	return (
		! isEmpty && (
			<figure { ...blockProps }>
				<table className="gkit-table">
					<thead className="gkit-table__header">
						<tr>{ Headers }</tr>
					</thead>
					<tbody { ...innerBlocksProps } />
					<tfoot className="gkit-table__footer">
						<tr>{ Footers }</tr>
					</tfoot>
				</table>
				{ caption && (
					<RichText.Content
						identifier="caption"
						tagName="figcaption"
						className="gkit-table__caption"
						value={ caption }
					/>
				) }
			</figure>
		)
	);
}
