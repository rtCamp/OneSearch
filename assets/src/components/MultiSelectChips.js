/**
 * WordPress dependencies
 */
import { Popover, Icon } from '@wordpress/components';

import { chevronDown, closeSmall } from '@wordpress/icons';

/**
 * External dependencies
 */
import { useState, useRef, useEffect } from 'react';
import { __ } from '@wordpress/i18n';

function MultiSelectChips( {
	label,
	placeholder = __( 'Select…', 'onesearch' ),
	options = [],
	value = [],
	onChange = () => {},
	disabled = false,
	valueField = 'value',
	labelField = 'label',
} ) {
	const [ open, setOpen ] = useState( false );
	const anchorRef = useRef( null );

	// If the parent disables the control while the menu is open, close it.
	useEffect( () => {
		if ( disabled && open ) {
			setOpen( false );
		}
	}, [ disabled, open ] );

	const valueSet = new Set( ( value || [] ).map( String ) );

	const getVal = ( val ) =>
		String( val?.[ valueField ] ?? val?.slug ?? val?.id ?? val?.key ?? '' );
	const getLabel = ( val ) =>
		String( val?.[ labelField ] ?? val?.label ?? val?.name ?? val?.slug ?? getVal( val ) );

	// Change the set based on toggled values.
	const toggleValue = ( val ) => {
		if ( disabled ) {
			return;
		}
		const key = String( val );
		const set = new Set( valueSet );

		if ( set.has( key ) ) {
			set.delete( key );
		} else {
			set.add( key );
		}

		onChange( Array.from( set ) );
	};

	const removeValue = ( val ) => {
		if ( disabled ) {
			return;
		}
		const key = String( val );
		onChange( ( value || [] ).filter( ( v ) => String( v ) !== key ) );
	};

	const selected = options.filter( ( val ) => valueSet.has( getVal( val ) ) );

	return (
		<div className="msc" aria-disabled={ disabled }>
			{ label && <span className="msc-label">{ label }</span> }
			<button
				ref={ anchorRef }
				type="button"
				className="msc-control"
				onClick={ () => ! disabled && setOpen( ( v ) => ! v ) }
				disabled={ disabled }
				aria-haspopup="listbox"
				aria-expanded={ open }
			>
				<div className={ `msc-chips ${ disabled ? 'msc--disabled' : '' }` }>
					{ selected.length === 0 ? (
						<span className="msc-placeholder">{ placeholder }</span>
					) : (
						selected.map( ( item ) => {
							const itemValue = getVal( item );
							const itemLabel = getLabel( item );

							const handleRemove = ( e ) => {
								e.stopPropagation();
								removeValue( itemValue );
							};

							return (
								<span key={ itemValue } className="msc-chip">
									<span className="msc-chip-label">{ itemLabel }</span>
									<button
										type="button"
										className="msc-chip-close"
										onClick={ handleRemove }
										aria-label={ `Remove ${ itemLabel }` }
									>
										<Icon icon={ closeSmall } aria-disabled={ disabled } />
									</button>
								</span>
							);
						} )
					) }
				</div>
				<Icon icon={ chevronDown } />
			</button>

			{ open && (
				<Popover anchor={ anchorRef.current } onClose={ () => setOpen( false ) }>
					<div
						className="msc-menu"
						role="listbox"
						style={ {
							minWidth: anchorRef.current?.offsetWidth,
						} }
					>
						<ul className="msc-list">
							{ options.map( ( item ) => {
								const val = getVal( item );
								const itemLabel = getLabel( item );
								const id = `msc-opt-${ encodeURIComponent( val ) };`;
								const checked = valueSet.has( val );
								return (
									<li
										key={ val }
										className="msc-row"
										role="option"
										aria-selected={ checked }
									>
										<label htmlFor={ id } className="msc-row-label">
											<input
												id={ id }
												type="checkbox"
												checked={ checked }
												onChange={ () => toggleValue( val ) }
												disabled={ disabled }
											/>
											<span className="msc-row-text">{ itemLabel }</span>
										</label>
									</li>
								);
							} ) }
						</ul>
					</div>
				</Popover>
			) }
		</div>
	);
}

export default MultiSelectChips;
