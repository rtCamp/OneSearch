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
		<Card className="onesearch-entities-card">
			<CardHeader>
				<h3>{ __( 'Brand Sites', 'onesearch' ) }</h3>
				<Button
					isPrimary
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

						{ sites.map( ( site, index ) => {
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
									<td>{ site.siteName }</td>
									<td>{ site.siteUrl }</td>
									<td><code>{ site.publicKey.slice( 0, 10 ) }…</code></td>

									<td>
										<Button
											variant="secondary"
											disabled={ isBusy }
											onClick={ handleEdit }
											className="onesearch-button-group"
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

			{ /* Confirmation modal */ }
			{ showDeleteModal && (
				<Modal
					title={ __( 'Delete Brand Site', 'onesearch' ) }
					onRequestClose={ cancelDelete }
					isDismissible
				>
					<p>
						{ __( 'Are you sure you want to delete this Brand Site? This action cannot be undone.', 'onesearch' ) }
					</p>

					<Button
						variant="secondary"
						isDestructive
						onClick={ confirmDelete }
					>
						{ __( 'Delete', 'onesearch' ) }
					</Button>

					<Button
						variant="secondary"
						onClick={ cancelDelete }
						className="onesearch-regenerate-key-button"
					>
						{ __( 'Cancel', 'onesearch' ) }
					</Button>
				</Modal>
			) }
		</Card>
	);
};

export default SiteTable;
