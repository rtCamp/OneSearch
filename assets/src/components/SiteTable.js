/**
 * WordPress dependencies
 */
import { useState } from '@wordpress/element';
import {
	Button,
	Card,
	CardHeader,
	CardBody,
	Modal,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const SiteTable = ( {
	sites,
	onEdit,
	onDelete,
	setFormData,
	setShowModal,
} ) => {
	const [ showDeleteModal, setShowDeleteModal ] = useState( false );
	const [ pendingIndex, setPendingIndex ] = useState( null ); // row waiting for confirm
	const [ busyIndex, setBusyIndex ] = useState( null ); // row being deleted

	const openDeleteModal = ( index ) => {
		setPendingIndex( index );
		setShowDeleteModal( true );
	};

	const cancelDelete = () => {
		setShowDeleteModal( false );
		setPendingIndex( null );
	};

	const confirmDelete = async () => {
		setShowDeleteModal( false );
		setBusyIndex( pendingIndex );
		try {
			await onDelete( pendingIndex );
		} finally {
			setBusyIndex( null );
			setPendingIndex( null );
		}
	};

	return (
		<Card style={ { marginTop: '30px' } }>
			<CardHeader>
				<h3>{ __( 'Brand Sites', 'onesearch' ) }</h3>

				<Button
					style={ { width: 'fit-content' } }
					variant="primary"
					onClick={ () => setShowModal( true ) }
					disabled={ busyIndex !== null }
				>
					{ __( 'Add Brand Site', 'onesearch' ) }
				</Button>
			</CardHeader>

			<CardBody>
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>{ __( 'Site Name', 'onesearch' ) }</th>
							<th>{ __( 'Site URL', 'onesearch' ) }</th>
							<th>{ __( 'API Key', 'onesearch' ) }</th>
							<th>{ __( 'Actions', 'onesearch' ) }</th>
						</tr>
					</thead>

					<tbody>
						{ sites.length === 0 && (
							<tr>
								<td colSpan="4">
									{ __( 'No Brand Sites found.', 'onesearch' ) }
								</td>
							</tr>
						) }

						{ sites?.map( ( site, index ) => {
							const isBusy = busyIndex === index;

							const handleEdit = () => {
								setFormData( site );
								onEdit( index );
								setShowModal( true );
							};

							const handleDelete = () => {
								openDeleteModal( index );
							};

							return (
								<tr key={ index }>
									<td>{ site.name }</td>
									<td>{ site.url }</td>
									<td><code>{ site.api_key.slice( 0, 10 ) }…</code></td>

									<td>
										<Button
											variant="secondary"
											onClick={ handleEdit }
											className="onesearch-button-group"
											disabled={ site?.is_editable === false || isBusy }
										>
											{ __( 'Edit', 'onesearch' ) }
										</Button>

										<Button
											variant="secondary"
											isDestructive
											disabled={ isBusy }
											onClick={ handleDelete }
											isBusy={ isBusy }
										>
											{ isBusy
												? __( 'Deleting…', 'onesearch' )
												: __( 'Delete', 'onesearch' ) }
										</Button>
									</td>
								</tr>
							);
						} ) }
					</tbody>
				</table>
			</CardBody>

			{ showDeleteModal && (
				<DeleteConfirmationModal
					onConfirm={ confirmDelete }
					onCancel={ cancelDelete }
				/>
			) }
		</Card>
	);
};

/**
 * DeleteConfirmationModal component for confirming site deletion.
 *
 * @param {Object}   props           - Component properties.
 * @param {Function} props.onConfirm - Function to call on confirmation.
 * @param {Function} props.onCancel  - Function to call on cancellation.
 * @return {JSX.Element} Rendered component.
 */
const DeleteConfirmationModal = ( { onConfirm, onCancel } ) => (
	<Modal
		title={ __( 'Delete Brand Site', 'onesearch' ) }
		onRequestClose={ onCancel }
		isDismissible={ true }
	>
		<p>{ __( 'Are you sure you want to delete this Brand Site? This action cannot be undone.', 'onesearch' ) }</p>
		<div style={ { display: 'flex', justifyContent: 'flex-end', marginTop: '20px', gap: '16px' } }>
			<Button
				variant="secondary"
				onClick={ onCancel }
			>
				{ __( 'Cancel', 'onesearch' ) }
			</Button>
			<Button
				variant="primary"
				isDestructive
				onClick={ onConfirm }
			>
				{ __( 'Delete', 'onesearch' ) }
			</Button>
		</div>
	</Modal>
);

export default SiteTable;
