import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	CardHeader,
	Snackbar,
	Card,
	CardBody,
	__experimentalGrid as Grid, // eslint-disable-line @wordpress/no-unsafe-wp-apis
	SelectControl,
	Button,
	Modal,
	TextareaControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
import { decodeEntities } from '@wordpress/html-entities';
import { arrowLeft } from '@wordpress/icons';

const NONCE = OneAccess.restNonce;
const API_NAMESPACE = OneAccess.restUrl + '/oneaccess/v1';
const API_KEY = OneAccess.apiKey;
const PER_PAGE = 20;
const PAGE = 1;

const ProfileRequests = ( { setProfileRequestsCount } ) => {
	const [ isLoading, setIsLoading ] = useState( false );
	const [ notice, setNotice ] = useState( {
		type: '',
		message: '',
	} );
	const [ searchTerm, setSearchTerm ] = useState( '' );
	const [ selectedSiteFilter, setSelectedSiteFilter ] = useState( '' );
	const [ requestStatusFilter, setRequestStatusFilter ] = useState( 'all' );
	const [ allSitesProfileRequests, setAllSitesProfileRequests ] = useState( [] );
	const [ isViewModalOpen, setIsViewModalOpen ] = useState( false );
	const [ isRejectModalOpen, setIsRejectModalOpen ] = useState( false );
	const [ selectedRequest, setSelectedRequest ] = useState( null );
	const [ rejectionComment, setRejectionComment ] = useState( '' );
	const [ isProcessing, setIsProcessing ] = useState( false );
	const [ page, setPage ] = useState( PAGE );
	const [ totalPages, setTotalPages ] = useState( 1 );

	const fetchProfileRequests = useCallback( async () => {
		setIsLoading( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/all-profile-requests?${ new Date().getTime().toString() }`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
						'X-OneAccess-Token': API_KEY,
					},
				},
			);

			if ( ! response.ok ) {
				setNotice( {
					type: 'error',
					message: __( 'Failed to fetch profile requests.', 'oneaccess' ),
				} );
				throw new Error( 'Failed to fetch profile requests' );
			}

			const data = await response.json();
			setAllSitesProfileRequests( data.profile_requests || [] );
			setProfileRequestsCount( data.count || 0 );
			setTotalPages( Math.ceil( ( data.count || 0 ) / PER_PAGE ) );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to fetch profile requests.', 'oneaccess' ),
			} );
		} finally {
			setIsLoading( false );
		}
	}, [ setProfileRequestsCount ] );

	const handleAcceptRequest = async ( request ) => {
		setIsProcessing( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/approve-profile-request`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
						'X-OneAccess-Token': API_KEY,
					},
					body: JSON.stringify( {
						user_email: request?.user_email,
						user_login: request?.user_login,
						site_name: request?.site_name,
						data: request?.data,
					} ),
				},
			);

			if ( ! response.ok ) {
				throw new Error( 'Failed to approve request' );
			}

			setNotice( {
				type: 'success',
				message: __( 'Profile request approved successfully.', 'oneaccess' ),
			} );
			fetchProfileRequests(); // Refresh the list
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to approve profile request.', 'oneaccess' ),
			} );
		} finally {
			setIsProcessing( false );
			setIsViewModalOpen( false );
		}
	};

	const handleRejectRequest = useCallback( async () => {
		if ( ! rejectionComment.trim() ) {
			setNotice( {
				type: 'error',
				message: __( 'Please provide a rejection comment.', 'oneaccess' ),
			} );
			return;
		}
		setIsProcessing( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/reject-profile-request`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
						'X-OneAccess-Token': API_KEY,
					},
					body: JSON.stringify( {
						user_email: selectedRequest?.user_email,
						site_name: selectedRequest?.site_name,
						rejection_comment: rejectionComment,
					} ),
				},
			);

			if ( ! response.ok ) {
				throw new Error( 'Failed to reject request' );
			}

			setNotice( {
				type: 'success',
				message: __( 'Profile request rejected successfully.', 'oneaccess' ),
			} );
			fetchProfileRequests(); // Refresh the list
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to reject profile request.', 'oneaccess' ),
			} );
		} finally {
			setIsProcessing( false );
			setIsRejectModalOpen( false );
			setIsViewModalOpen( false );
			setRejectionComment( '' );
		}
	}, [ rejectionComment, selectedRequest, fetchProfileRequests ] );

	const openViewModal = ( request ) => {
		setSelectedRequest( request );
		setIsViewModalOpen( true );
	};

	const openRejectModal = () => {
		setIsRejectModalOpen( true );
	};

	const closeModals = () => {
		setIsViewModalOpen( false );
		setRejectionComment( '' );
	};

	const renderChangesTable = ( request ) => {
		const changes = [];

		// Add metadata changes
		if ( request.metadata ) {
			Object.entries( request.metadata ).forEach( ( [ key, value ] ) => {
				changes.push( {
					field: key.replace( /_/g, ' ' ).replace( /\b\w/g, ( l ) => l.toUpperCase() ),
					oldValue: value.old || __( 'Empty', 'oneaccess' ),
					newValue: value.new || __( 'Empty', 'oneaccess' ),
				} );
			} );
		}

		// Add data changes
		if ( request.data ) {
			Object.entries( request.data ).forEach( ( [ key, value ] ) => {
				changes.push( {
					field: key.replace( /_/g, ' ' ).replace( /\b\w/g, ( l ) => l.toUpperCase() ),
					oldValue: value.old || __( 'Empty', 'oneaccess' ),
					newValue: value.new || __( 'Empty', 'oneaccess' ),
				} );
			} );
		}

		return (
			<table className="wp-list-table widefat fixed striped" style={ { marginTop: '16px' } }>
				<thead>
					<tr>
						<th>{ __( 'Field', 'oneaccess' ) }</th>
						<th>{ __( 'Old Value', 'oneaccess' ) }</th>
						<th>{ __( 'New Value', 'oneaccess' ) }</th>
					</tr>
				</thead>
				<tbody>
					{ changes.length === 0 ? (
						<tr>
							<td colSpan="3" style={ { textAlign: 'center' } }>
								{ __( 'No changes found.', 'oneaccess' ) }
							</td>
						</tr>
					) : (
						changes?.map( ( change, index ) => (
							<tr key={ index }>
								<td><strong>{ change.field }</strong></td>
								<td>{ decodeEntities( change.oldValue ) }</td>
								<td>{ decodeEntities( change.newValue ) }</td>
							</tr>
						) )
					) }
				</tbody>
			</table>
		);
	};

	// Filter and search functionality
	const filteredRequests = allSitesProfileRequests.filter( ( request ) => {
		// Site filter
		const matchesSite = selectedSiteFilter === '' ||
			request.site_name.toLowerCase().includes( selectedSiteFilter.toLowerCase() );

		// Search filter
		const searchLower = searchTerm.toLowerCase();
		const matchesSearch = searchTerm === '' ||
			request.user_name.toLowerCase().includes( searchLower ) ||
			request.user_email.toLowerCase().includes( searchLower ) ||
			request.requested_by.toLowerCase().includes( searchLower );

		// Status filter
		const matchesStatus = requestStatusFilter === 'all' ||
			request.status === requestStatusFilter;

		return matchesSite && matchesSearch && matchesStatus;
	} );

	// Get unique site names for filter dropdown
	const siteOptions = [
		{ label: __( 'All Sites', 'oneaccess' ), value: '' },
		...Array.from( new Set( allSitesProfileRequests?.map( ( r ) => r.site_name ) ) )
			?.map( ( siteName ) => ( { label: siteName, value: siteName } ) ),
	];

	// request status filter options
	const statusOptions = [
		{ label: __( 'All Status', 'oneaccess' ), value: 'all' },
		{ label: __( 'Pending', 'oneaccess' ), value: 'pending' },
		{ label: __( 'Rejected', 'oneaccess' ), value: 'rejected' },
	];

	const getOptionByValue = ( value ) => {
		return statusOptions.find( ( option ) => option.value === value )?.label;
	};

	useEffect( () => {
		fetchProfileRequests();
	}, [] ); /* eslint-disable-line react-hooks/exhaustive-deps */

	const filterUserRequestsForPagination = useCallback( () => {
		const startIndex = ( page - 1 ) * PER_PAGE;
		return filteredRequests.slice( startIndex, startIndex + PER_PAGE );
	}, [ filteredRequests, page ] );

	useEffect( () => {
		setTotalPages( Math.ceil( filteredRequests.length / PER_PAGE ) );
	}, [ filteredRequests ] );

	return (
		<>
			<Card>
				<CardHeader>
					<h2>{ __( 'Profile Update Requests', 'oneaccess' ) }</h2>
				</CardHeader>
				<CardBody>
					<Grid columns="4" gap="4" style={ { alignItems: 'flex-end' } }>
						<TextControl
							placeholder={ __( 'Search by name, email, or requested by…', 'oneaccess' ) }
							value={ searchTerm }
							onChange={ setSearchTerm }
							label={ __( 'Search Requests', 'oneaccess' ) }
						/>
						<SelectControl
							label={ __( 'Filter by site', 'oneaccess' ) }
							value={ selectedSiteFilter }
							options={ siteOptions }
							onChange={ setSelectedSiteFilter }
						/>
						<SelectControl
							label={ __( 'Filter by status', 'oneaccess' ) }
							value={ requestStatusFilter }
							options={ statusOptions }
							onChange={ setRequestStatusFilter }
						/>
					</Grid>
					<table className="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th>{ __( 'Site', 'oneaccess' ) }</th>
								<th>{ __( 'User', 'oneaccess' ) }</th>
								<th>{ __( 'Email', 'oneaccess' ) }</th>
								<th>{ __( 'Requested by', 'oneaccess' ) }</th>
								<th>{ __( 'Requested at', 'oneaccess' ) }</th>
								<th>{ __( 'Status', 'oneaccess' ) }</th>
								<th>{ __( 'Actions', 'oneaccess' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ isLoading && (
								<tr>
									<td colSpan="7" style={ { textAlign: 'center', padding: '20px' } }>
										<Spinner />
									</td>
								</tr>
							) }
							{ ! isLoading && filteredRequests?.length === 0 && (
								<tr>
									<td colSpan="7" style={ { textAlign: 'center' } }>
										{ __( 'No profile requests found.', 'oneaccess' ) }
									</td>
								</tr>
							) }
							{ filterUserRequestsForPagination()?.length > 0 && (
								filterUserRequestsForPagination()?.map( ( request, index ) => (
									<tr key={ `${ request.user_email }-${ request.site_name }-${ index }` }>
										<td>{ decodeEntities( request.site_name ) }</td>
										<td>{ decodeEntities( request.user_name ) }</td>
										<td>{ request.user_email }</td>
										<td>{ decodeEntities( request.requested_by ) }</td>
										<td>{ request.requested_at }</td>
										<td>
											<span
												className={ `status-badge status-${ getOptionByValue( request.status ) ?? request.status }` }
												style={ {
													display: 'inline-block',
													padding: '2px 8px',
													borderRadius: '4px',
													fontSize: '12px',
													fontWeight: '400',
													backgroundColor: request.status === 'pending' ? '#ffc107' : '#dc3545',
													color: request.status === 'pending' ? '#000' : '#fff',
												} }
											>
												{ getOptionByValue( request.status ) ?? request.status }
											</span>
										</td>
										<td>
											<Button
												variant="primary"
												onClick={ () => openViewModal( request ) }
												disabled={ isProcessing }
											>
												{ __( 'View Details', 'oneaccess' ) }
											</Button>
										</td>
									</tr>
								) )
							) }
						</tbody>
					</table>
					{ (
						<div style={ { marginTop: '16px', display: 'flex', justifyContent: 'center' } }>
							<Button
								variant="secondary"
								onClick={ () => setPage( ( prev ) => Math.max( prev - 1, 1 ) ) }
								disabled={ page === 1 }
								style={ { marginRight: '8px' } }
							>
								{ __( 'Previous', 'oneaccess' ) }
							</Button>
							<span style={ { alignSelf: 'center' } }>
								{ __( 'Page', 'oneaccess' ) } { page } { __( 'of', 'oneaccess' ) } { totalPages === 0 ? 1 : totalPages }
							</span>
							<Button
								variant="secondary"
								onClick={ () => setPage( ( prev ) => Math.min( prev + 1, totalPages ) ) }
								disabled={ page >= totalPages }
								style={ { marginLeft: '8px' } }
							>
								{ __( 'Next', 'oneaccess' ) }
							</Button>
						</div>
					) }
				</CardBody>
			</Card>

			{ /* View Request Modal */ }
			{ isViewModalOpen && selectedRequest && (
				<Modal
					title={ __( 'Profile Requested Changes', 'oneaccess' ) }
					onRequestClose={ closeModals }
					style={ { minWidth: '600px' } }
					size="medium"
					shouldCloseOnClickOutside={ true }
				>
					<div style={ { marginBottom: '20px', display: 'grid', gridTemplateColumns: 'repeat(2,1fr)' } }>
						<p style={ { margin: '0' } } ><strong>{ __( 'User:', 'oneaccess' ) }</strong> { decodeEntities( selectedRequest.user_name ) }</p>
						<p style={ { margin: '0' } }><strong>{ __( 'Email:', 'oneaccess' ) }</strong> { selectedRequest.user_email }</p>
						<p style={ { margin: '0' } }><strong>{ __( 'Site:', 'oneaccess' ) }</strong> { decodeEntities( selectedRequest.site_name ) }</p>
						<p style={ { margin: '0' } }><strong>{ __( 'Requested By:', 'oneaccess' ) }</strong> { decodeEntities( selectedRequest.requested_by ) }</p>
						<p style={ { margin: '0' } }><strong>{ __( 'Requested At:', 'oneaccess' ) }</strong> { selectedRequest.requested_at }</p>
						<p style={ { margin: '0' } }><strong>{ __( 'Status:', 'oneaccess' ) }</strong> { selectedRequest.status }</p>
					</div>

					<h3>{ __( 'Requested Changes:', 'oneaccess' ) }</h3>
					{ renderChangesTable( selectedRequest ) }

					{ selectedRequest.status === 'pending' && (
						<div style={ { marginTop: '20px', display: 'flex', gap: '10px', justifyContent: 'flex-end' } }>
							<Button
								variant="secondary"
								isDestructive
								onClick={ openRejectModal }
								disabled={ isProcessing }
							>
								{ __( 'Reject', 'oneaccess' ) }
							</Button>
							<Button
								variant="primary"
								onClick={ () => handleAcceptRequest( selectedRequest ) }
								disabled={ isProcessing }
							>
								{ isProcessing ? __( 'Processing…', 'oneaccess' ) : __( 'Accept', 'oneaccess' ) }
							</Button>
						</div>
					) }
					{
						selectedRequest.status === 'rejected' && selectedRequest.rejection_comment.trim().length > 0 && (
							<div style={ { marginTop: '20px', background: '#fee2e2', borderLeft: '4px solid #dc2626', borderRadius: '4px', padding: '12px 16px' } }>
								<p style={ { fontSize: '15px', color: '#7f1d1d', margin: '0' } }>
									<strong style={ { color: '#991b1b', fontWeight: '600' } }>{ __( 'Rejection Comment: ', 'oneaccess' ) }</strong>
									<span>{ decodeEntities( selectedRequest.rejection_comment ) }</span>
								</p>
							</div>
						)
					}
				</Modal>
			) }

			{ /* Reject Request Modal */ }
			{ isRejectModalOpen && (
				<Modal
					title={ __( 'Reject Profile Updates Changes', 'oneaccess' ) }
					onRequestClose={ () => setIsRejectModalOpen( false ) }
					size="medium"
					shouldCloseOnClickOutside={ true }
				>
					<p>{ __( 'Please provide a reason for rejecting this profile update request:', 'oneaccess' ) }</p>
					<TextareaControl
						placeholder={ __( 'Enter rejection reason…', 'oneaccess' ) }
						value={ rejectionComment }
						onChange={ setRejectionComment }
						rows={ 4 }
					/>
					<div style={ { marginTop: '20px', display: 'flex', gap: '10px', justifyContent: 'flex-end' } }>
						<Button
							variant="secondary"
							onClick={ () => {
								setIsRejectModalOpen( false );
								setRejectionComment( '' );
								setIsViewModalOpen( true );
							} }
							disabled={ isProcessing }
							icon={ arrowLeft }
						>
							{ __( 'Back', 'oneaccess' ) }
						</Button>
						<Button
							variant="primary"
							onClick={ handleRejectRequest }
							isDestructive
							disabled={ isProcessing || ! rejectionComment.trim() }
						>
							{ isProcessing ? __( 'Rejecting…', 'oneaccess' ) : __( 'Reject Request', 'oneaccess' ) }
						</Button>
					</div>
				</Modal>
			) }

			{ notice.message && (
				<Snackbar
					isDismissible
					status={ notice.type }
					className={ notice.type === 'error' ? 'oneaccess-error-notice' : 'oneaccess-success-notice' }
					onRemove={ () => setNotice( { type: '', message: '' } ) }
				>
					{ notice.message }
				</Snackbar>
			) }
		</>
	);
};

export default ProfileRequests;
