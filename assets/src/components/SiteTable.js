import { useState } from '@wordpress/element';
import { Button, Card, CardHeader, CardBody, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const SiteTable = ( { sites, onEdit, onDelete, setFormData, setShowModal } ) => {
	const [ showDeleteModal, setShowDeleteModal ] = useState( false );
	const [ deleteIndex, setDeleteIndex ] = useState( null );

	const handleDeleteClick = ( index ) => {
		setDeleteIndex( index );
		setShowDeleteModal( true );
	};

	const handleDeleteConfirm = () => {
		onDelete( deleteIndex );
		setShowDeleteModal( false );
		setDeleteIndex( null );
	};

	const handleDeleteCancel = () => {
		setShowDeleteModal( false );
		setDeleteIndex( null );
	};

	return (
		<Card style={ { marginTop: '30px' } }>
			<CardHeader>
				<h3>{ __( 'Brand Sites', 'oneaccess' ) }</h3>
				<Button
					style={ { width: 'fit-content' } }
					variant="primary"
					onClick={ () => setShowModal( true ) }
				>
					{ __( 'Add Brand Site', 'oneaccess' ) }
				</Button>
			</CardHeader>
			<CardBody>
				<table className="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>{ __( 'Site Name', 'oneaccess' ) }</th>
							<th>{ __( 'Site URL', 'oneaccess' ) }</th>
							<th>{ __( 'API Key', 'oneaccess' ) }</th>
							<th>{ __( 'Actions', 'oneaccess' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ sites.length === 0 && (
							<tr>
								<td colSpan="4" style={ { textAlign: 'center' } }>
									{ __( 'No Brand Sites found.', 'oneaccess' ) }
								</td>
							</tr>
						) }
						{ sites?.map( ( site, index ) => (
							<tr key={ index }>
								<td>{ site?.siteName }</td>
								<td>{ site?.siteUrl }</td>
								<td><code>{ site.apiKey.substring( 0, 10 ) }...</code></td>
								<td>
									<Button
										variant="secondary"
										onClick={ () => {
											setFormData( site );
											onEdit( index );
											setShowModal( true );
										} }
										style={ { marginRight: '8px' } }
									>
										{ __( 'Edit', 'oneaccess' ) }
									</Button>
									<Button
										variant="secondary"
										isDestructive
										onClick={ () => handleDeleteClick( index ) }
									>
										{ __( 'Delete', 'oneaccess' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			</CardBody>
			{ showDeleteModal && (
				<DeleteConfirmationModal
					onConfirm={ handleDeleteConfirm }
					onCancel={ handleDeleteCancel }
				/>
			) }
		</Card>
	);
};

const DeleteConfirmationModal = ( { onConfirm, onCancel } ) => (
	<Modal
		title={ __( 'Delete Brand Site', 'oneaccess' ) }
		onRequestClose={ onCancel }
		isDismissible={ true }
		shouldCloseOnClickOutside={ true }
	>
		<p>{ __( 'Are you sure you want to delete this Brand Site? This action cannot be undone.', 'oneaccess' ) }</p>
		<div style={ { display: 'flex', justifyContent: 'flex-end', marginTop: '20px', gap: '16px' } }>
			<Button
				variant="secondary"
				onClick={ onCancel }
			>
				{ __( 'Cancel', 'oneaccess' ) }
			</Button>
			<Button
				variant="primary"
				isDestructive
				onClick={ onConfirm }
			>
				{ __( 'Delete', 'oneaccess' ) }
			</Button>
		</div>
	</Modal>
);

export default SiteTable;
