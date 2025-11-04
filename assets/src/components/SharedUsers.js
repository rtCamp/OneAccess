/**
 * WordPress dependencies
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	Card,
	CardHeader,
	CardBody,
	Spinner,
	Button,
	Snackbar,
	SelectControl,
	Modal,
	DropdownMenu,
	CheckboxControl,
	Notice,
	__experimentalVStack as VStack,
	__experimentalHStack as HStack,
	Dashicon,
	__experimentalGrid as Grid,
	MenuGroup,
	MenuItem,
	TextControl,
} from '@wordpress/components';
import { moreVertical, people, plus, Icon, globe, trash } from '@wordpress/icons';
import { decodeEntities } from '@wordpress/html-entities';

const NONCE = OneAccess.restNonce;
const API_NAMESPACE = OneAccess.restUrl + '/oneaccess/v1';
const AVAILABLE_ROLES = OneAccess.availableRoles || [];
const PER_PAGE = 20;

const SharedUsers = ( { availableSites } ) => {
	const [ isLoading, setIsLoading ] = useState( false );
	const [ notice, setNotice ] = useState( {
		type: '',
		message: '',
	} );
	const [ users, setUsers ] = useState( [] );
	const [ searchTerm, setSearchTerm ] = useState( '' );
	const [ selectedSiteFilter, setSelectedSiteFilter ] = useState( '' );
	const [ selectedUserRole, setSelectedUserRole ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ _, setTotalUsers ] = useState( 0 );

	// Modal states
	const [ showManageRolesModal, setShowManageRolesModal ] = useState( false );
	const [ showAddToSitesModal, setShowAddToSitesModal ] = useState( false );
	const [ selectedUser, setSelectedUser ] = useState( null );
	const [ userRoles, setUserRoles ] = useState( {} );
	const [ selectedSitesToAdd, setSelectedSitesToAdd ] = useState( [] );
	const [ isUpdatingRoles, setIsUpdatingRoles ] = useState( false );
	const [ isAddingToSites, setIsAddingToSites ] = useState( false );
	const [ showUserDeletionModal, setShowUserDeletionModal ] = useState( false );
	const [ selectedSitesToDeleteUser, setSelectedSitesToDeleteUser ] = useState( [] );
	const [ isDeletingUser, setIsDeletingUser ] = useState( false );

	const fetchUsers = useCallback( async () => {
		setIsLoading( true );
		try {
			// Build query parameters
			const params = new URLSearchParams( {
				paged: page.toString(),
				per_page: PER_PAGE.toString(),
			} );

			// Add optional filters
			if ( searchTerm ) {
				params.append( 'search_query', searchTerm );
			}
			if ( selectedUserRole ) {
				params.append( 'role', selectedUserRole );
			}
			if ( selectedSiteFilter ) {
				params.append( 'site', selectedSiteFilter );
			}

			const response = await fetch(
				`${ API_NAMESPACE }/new-users?${ params.toString() }`,
				{
					method: 'GET',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
				},
			);

			if ( ! response.ok ) {
				throw new Error( 'Failed to fetch users' );
			}

			const data = await response.json();

			if ( ! data.success ) {
				throw new Error( data.message || 'Failed to fetch users' );
			}

			// Transform the API response to match component's expected format
			const transformedUsers = data.users.map( ( user ) => ( {
				id: user.id,
				username: user.email.split( '@' )[ 0 ], // Fallback username from email
				email: user.email,
				full_name: user.full_name || `${ user.first_name } ${ user.last_name }`.trim() || user.email,
				first_name: user.first_name,
				last_name: user.last_name,
				sites: user.sites_info?.map( ( site ) => ( {
					site_url: site.site_url,
					site_name: site.site_name,
					siteName: site.site_name,
					siteUrl: site.site_url,
					// Get the first role from roles array, or handle object structure
					role: ( () => {
						if ( Array.isArray( site.roles ) ) {
							return site.roles[ 0 ];
						}
						if ( typeof site.roles === 'object' && site.roles !== null ) {
							return Object.values( site.roles )[ 0 ];
						}
						return 'subscriber';
					} )(),
					roles: site.roles,
					user_id: site.user_id,
				} ) ) || [],
				sites_info: user.sites_info,
				all_roles: user.all_roles || [],
				all_sites: user.all_sites || [],
				created_at: user.created_at,
				updated_at: user.updated_at,
			} ) );

			setUsers( transformedUsers );
			setTotalUsers( data.pagination.total_users );
			setTotalPages( data.pagination.total_pages );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to fetch users. Please try again later.', 'oneaccess' ),
			} );
		} finally {
			setIsLoading( false );
		}
	}, [ page, searchTerm, selectedUserRole, selectedSiteFilter ] );

	useEffect( () => {
		fetchUsers();
	}, [ fetchUsers ] );

	// Reset to page 1 when filters change
	useEffect( () => {
		if ( page !== 1 ) {
			setPage( 1 );
		}
	}, [ searchTerm, selectedUserRole, selectedSiteFilter ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Get sites available for adding (sites user is not already assigned to)
	const getAvailableSitesForUser = ( user ) => {
		const userSiteUrls = user.sites?.map( ( site ) => site.site_url ) || [];
		return availableSites.filter( ( site ) => ! userSiteUrls.includes( site.siteUrl ) );
	};

	// Handle opening manage roles modal
	const handleManageRoles = ( user ) => {
		setSelectedUser( user );

		// Initialize user roles with current roles
		const initialRoles = {};
		user.sites?.forEach( ( site ) => {
			initialRoles[ site.site_url ] = site.role || '';
		} );

		setUserRoles( initialRoles );
		setShowManageRolesModal( true );
	};

	// Handle opening add to sites modal
	const handleAddToSites = ( user ) => {
		setSelectedUser( user );
		setSelectedSitesToAdd( [] );
		setShowAddToSitesModal( true );
	};

	const handleUserDeletion = ( user ) => {
		setSelectedUser( user );
		setSelectedSitesToDeleteUser( [] );
		setShowUserDeletionModal( true );
	};

	const handleUserDeletionFromSelectedSites = useCallback( async () => {
		setIsDeletingUser( true );
		try {
			const currentUser = selectedUser;
			const sitesToDelete = selectedSitesToDeleteUser;

			const response = await fetch(
				`${ API_NAMESPACE }/delete-user-from-sites`,
				{
					method: 'DELETE',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
					body: JSON.stringify( {
						username: currentUser.username,
						email: currentUser.email,
						sites: sitesToDelete,
					} ),
				},
			);

			if ( ! response.ok ) {
				throw new Error( 'Failed to delete user from sites' );
			}

			const data = await response.json();
			if ( ! data.success ) {
				throw new Error( data.message || 'Failed to delete user from sites' );
			}

			setNotice( {
				type: 'success',
				message: __( 'User deleted successfully.', 'oneaccess' ),
			} );

			// Refresh users list
			await fetchUsers();
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to delete user.', 'oneaccess' ),
			} );
		} finally {
			setIsDeletingUser( false );
			setShowUserDeletionModal( false );
			setSelectedUser( null );
			setSelectedSitesToDeleteUser( [] );
		}
	}, [ selectedSitesToDeleteUser, fetchUsers, selectedUser ] );

	// Handle updating user roles
	const handleUpdateRoles = useCallback( async () => {
		setIsUpdatingRoles( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/update-user-roles`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
					body: JSON.stringify( {
						username: selectedUser.username,
						email: selectedUser.email,
						roles: userRoles,
					} ),
				},
			);

			if ( ! response.ok ) {
				throw new Error( 'Failed to update roles' );
			}

			const data = await response.json();
			if ( ! data.success ) {
				throw new Error( data.message || 'Failed to update roles' );
			}

			setNotice( {
				type: 'success',
				message: __( 'User roles updated successfully.', 'oneaccess' ),
			} );

			// Refresh users list
			await fetchUsers();
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to update user roles. Please try again.', 'oneaccess' ),
			} );
		} finally {
			setIsUpdatingRoles( false );
			setShowManageRolesModal( false );
		}
	}, [ userRoles, fetchUsers, selectedUser ] );

	// Handle adding user to sites
	const handleAddUserToSites = useCallback( async () => {
		setIsAddingToSites( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/add-user-to-sites`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
					},
					body: JSON.stringify( {
						username: selectedUser.username,
						fullName: selectedUser.full_name,
						password: selectedUser.password,
						email: selectedUser.email,
						sites: selectedSitesToAdd,
					} ),
				},
			);

			if ( ! response.ok ) {
				throw new Error( 'Failed to add user to sites' );
			}

			const data = await response.json();
			if ( ! data.success ) {
				throw new Error( data.message || 'Failed to add user to sites' );
			}

			const siteNames = selectedSitesToAdd?.map( ( site ) => site.siteName ).join( ', ' );
			setNotice( {
				type: 'success',
				message: __( 'User added to sites successfully: ', 'oneaccess' ) + siteNames,
			} );

			// Refresh users list
			await fetchUsers();
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to add user to sites. Please try again.', 'oneaccess' ),
			} );
		} finally {
			setIsAddingToSites( false );
			setShowAddToSitesModal( false );
		}
	}, [ selectedSitesToAdd, fetchUsers, selectedUser ] );

	// Handle page change
	const handlePageChange = ( newPage ) => {
		setPage( newPage );
	};

	// Get role label from role value
	const getRoleLabel = ( roleValue ) => {
		return AVAILABLE_ROLES[ roleValue ] || ( roleValue ?? __( 'No Role', 'oneaccess' ) );
	};

	return (
		<>
			<Card>
				<CardHeader>
					<h2>{ __( 'Shared Users', 'oneaccess' ) }</h2>
				</CardHeader>
				<CardBody>
					<Grid columns="4" gap="4" style={ { alignItems: 'flex-end' } }>
						<TextControl
							placeholder={ __( 'Search users by name or email..', 'oneaccess' ) }
							value={ searchTerm }
							onChange={ ( value ) => setSearchTerm( value ) }
							label={ __( 'Search Users', 'oneaccess' ) }
						/>
						<SelectControl
							label={ __( 'Filter by site', 'oneaccess' ) }
							value={ selectedSiteFilter }
							options={ [
								{ label: __( 'All Sites', 'oneaccess' ), value: '' },
								...availableSites?.map( ( site ) => ( {
									label: site.siteName,
									value: site.siteUrl,
								} ) ),
							] }
							onChange={ setSelectedSiteFilter }
						/>
						<SelectControl
							label={ __( 'Filter by role', 'oneaccess' ) }
							value={ selectedUserRole }
							options={ [
								{ label: __( 'All Roles', 'oneaccess' ), value: '' },
								...Object.entries( AVAILABLE_ROLES )?.map( ( [ role, label ] ) => ( {
									label,
									value: role,
								} ) ),
							] }
							onChange={ setSelectedUserRole }
						/>
					</Grid>

					<table className="wp-list-table widefat fixed striped" style={ { marginTop: '16px' } }>
						<thead>
							<tr>
								<th>{ __( 'Name', 'oneaccess' ) }</th>
								<th>{ __( 'Email', 'oneaccess' ) }</th>
								<th>{ __( 'Sites', 'oneaccess' ) }</th>
								<th>{ __( 'Actions', 'oneaccess' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ isLoading ? (
								<tr>
									<td colSpan="4" style={ { textAlign: 'center', padding: '32px' } }>
										<Spinner />
										<div style={ { marginTop: '8px' } }>{ __( 'Loading users…', 'oneaccess' ) }</div>
									</td>
								</tr>
							) : null }

							{ ! isLoading && users.length === 0 && (
								<tr>
									<td colSpan="4" style={ { textAlign: 'center', padding: '32px' } }>
										<p style={ { margin: 0, color: '#6c757d' } }>
											{ __( 'No users found.', 'oneaccess' ) }
										</p>
										{ ( searchTerm || selectedUserRole || selectedSiteFilter ) && (
											<Button
												variant="link"
												onClick={ () => {
													setSearchTerm( '' );
													setSelectedUserRole( '' );
													setSelectedSiteFilter( '' );
												} }
												style={ { marginTop: '8px' } }
											>
												{ __( 'Clear filters', 'oneaccess' ) }
											</Button>
										) }
									</td>
								</tr>
							) }

							{ ! isLoading && users?.map( ( user, index ) => (
								<tr key={ `${ user.id }-${ index }` }>
									<td>
										<strong>{ decodeEntities( user.full_name || user.username ) }</strong>
									</td>
									<td>{ user.email }</td>
									<td>
										<div
											style={ {
												display: 'flex',
												flexDirection: 'row',
												alignItems: 'center',
												gap: '4px',
												flexWrap: 'wrap',
											} }
										>
											{ user.sites?.length > 0 ? (
												user.sites?.map( ( site, siteIndex ) => (
													<span
														key={ `${ site.site_url }-${ siteIndex }` }
														className="site-badge"
														style={ {
															display: 'inline-block',
															margin: '0',
															padding: '4px 10px',
															backgroundColor: '#007cba',
															color: '#fff',
															borderRadius: '4px',
															fontSize: '12px',
															fontWeight: '500',
														} }
														title={ `${ site.site_name } - ${ getRoleLabel( site.role ) }` }
													>
														{ decodeEntities( site.site_name ) } ({ getRoleLabel( site.role ) })
													</span>
												) )
											) : (
												<span style={ { color: '#6c757d', fontSize: '12px' } }>
													{ __( 'No sites assigned', 'oneaccess' ) }
												</span>
											) }
										</div>
									</td>
									<td>
										<DropdownMenu
											icon={ moreVertical }
											label={ __( 'User actions', 'oneaccess' ) }
											className="user-actions-dropdown"
											popoverProps={ { position: 'bottom left' } }
										>
											{ ( { onClose } ) => (
												<>
													<MenuGroup>
														<MenuItem
															icon={ people }
															onClick={ () => {
																handleManageRoles( user );
																onClose();
															} }
															disabled={ ! user.sites || user.sites.length === 0 }
														>
															{ __( 'Manage Roles', 'oneaccess' ) }
														</MenuItem>
														{ getAvailableSitesForUser( user ).length > 0 && (
															<MenuItem
																icon={ globe }
																onClick={ () => {
																	handleAddToSites( user );
																	onClose();
																} }
															>
																{ __( 'Add to Sites', 'oneaccess' ) }
															</MenuItem>
														) }
													</MenuGroup>
													<MenuGroup>
														<MenuItem
															icon={ trash }
															onClick={ () => {
																handleUserDeletion( user );
																onClose();
															} }
															isDestructive
															disabled={ ! user.sites || user.sites.length === 0 }
														>
															{ __( 'Delete User', 'oneaccess' ) }
														</MenuItem>
													</MenuGroup>
												</>
											) }
										</DropdownMenu>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>

					{ totalPages > 1 && (
						<div style={ { marginTop: '16px', display: 'flex', gap: '8px', alignItems: 'center', justifyContent: 'center' } }>
							<Button
								variant="secondary"
								onClick={ () => handlePageChange( page - 1 ) }
								disabled={ page === 1 || isLoading }
							>
								{ __( 'Previous', 'oneaccess' ) }
							</Button>
							<div style={ { color: '#6c757d', fontSize: '14px' } }>
								{
									sprintf(
										/* translators: 1: Current page number. 2: Total pages. */
										__( 'Page %1$s of %2$s', 'oneaccess' ),
										page,
										totalPages,
									)
								}
							</div>
							<Button
								variant="secondary"
								onClick={ () => handlePageChange( page + 1 ) }
								disabled={ page >= totalPages || isLoading }
							>
								{ __( 'Next', 'oneaccess' ) }
							</Button>
						</div>
					) }
				</CardBody>
			</Card>

			{ /* Manage Roles Modal */ }
			{ showManageRolesModal && selectedUser && (
				<Modal
					title={ __( 'Manage User Roles', 'oneaccess' ) }
					onRequestClose={ () => setShowManageRolesModal( false ) }
					shouldCloseOnClickOutside={ true }
					size="medium"
				>
					<VStack spacing="4">
						<div>
							<p style={ { margin: 0, color: '#6c757d', fontSize: '14px' } }>
								{ __( 'Manage roles for user: ', 'oneaccess' ) }
								<strong>{ selectedUser.full_name || selectedUser.username }</strong> ({ selectedUser.email })
							</p>
						</div>

						<div
							style={ {
								maxHeight: '400px',
								overflowY: 'auto',
								border: '1px solid #e1e5e9',
								borderRadius: '8px',
								padding: '16px',
							} }
						>
							<VStack spacing="3">
								{ selectedUser.sites?.map( ( site, index ) => (
									<div
										key={ index }
										style={ {
											padding: '12px',
											border: '1px solid #f0f0f1',
											borderRadius: '4px',
										} }
									>
										<div style={ { marginBottom: '8px' } }>
											<div style={ { fontWeight: '500', color: '#23282d' } }>
												{ site.site_name }
											</div>
											<div style={ { fontSize: '12px', color: '#6c757d' } }>
												{ site.site_url }
											</div>
										</div>
										<SelectControl
											value={ userRoles[ site.site_url ] || site.role || '' }
											options={ [
												...Object.entries( AVAILABLE_ROLES )?.map( ( [ role, label ] ) => ( {
													value: role,
													label,
												} ) ),
											] }
											onChange={ ( value ) => {
												setUserRoles( ( prev ) => ( {
													...prev,
													[ site.site_url ]: value,
												} ) );
											} }
										/>
									</div>
								) ) }
							</VStack>
						</div>

						<HStack justify="flex-end" spacing="3">
							<Button
								variant="secondary"
								onClick={ () => setShowManageRolesModal( false ) }
							>
								{ __( 'Cancel', 'oneaccess' ) }
							</Button>
							<Button
								variant="primary"
								onClick={ handleUpdateRoles }
								disabled={ isUpdatingRoles || selectedUser.sites.every( ( site ) => userRoles[ site.site_url ] === site.role ) }
								isBusy={ isUpdatingRoles }
							>
								<Dashicon icon="admin-users" style={ { marginRight: '8px' } } />
								{ isUpdatingRoles ? __( 'Updating Roles…', 'oneaccess' ) : __( 'Update Roles', 'oneaccess' ) }
							</Button>
						</HStack>
					</VStack>
				</Modal>
			) }

			{ /* Add to Sites Modal */ }
			{ showAddToSitesModal && selectedUser && (
				<Modal
					title={ __( 'Add User to Sites', 'oneaccess' ) }
					onRequestClose={ () => setShowAddToSitesModal( false ) }
					shouldCloseOnClickOutside={ true }
					size="medium"
				>
					<VStack spacing="4">
						<div>
							<p style={ { margin: 0, color: '#6c757d', fontSize: '14px' } }>
								{ __( 'Add user to additional sites: ', 'oneaccess' ) }
								<strong>{ selectedUser.full_name || selectedUser.username }</strong> ({ selectedUser.email })
							</p>
						</div>

						{ getAvailableSitesForUser( selectedUser ).length > 0 ? (
							<>
								<HStack justify="flex-start" spacing="3">
									<CheckboxControl
										label={ __( 'Select All Sites', 'oneaccess' ) }
										checked={ selectedSitesToAdd.length === getAvailableSitesForUser( selectedUser ).length }
										onChange={ () => {
											const availableSitesToAddUser = getAvailableSitesForUser( selectedUser );
											if ( selectedSitesToAdd.length === availableSitesToAddUser.length ) {
												setSelectedSitesToAdd( [] );
											} else {
												setSelectedSitesToAdd(
													availableSitesToAddUser?.map( ( site ) => ( {
														siteUrl: site.siteUrl,
														siteName: site.siteName,
														apiKey: site.apiKey,
														role: site.role || 'subscriber',
													} ) ),
												);
											}
										} }
									/>
									<Button
										variant="link"
										onClick={ () => setSelectedSitesToAdd( [] ) }
										disabled={ selectedSitesToAdd.length === 0 }
										style={ { marginBlockEnd: '0.5rem' } }
									>
										{ __( 'Clear Selection', 'oneaccess' ) }
									</Button>
								</HStack>

								<div
									style={ {
										maxHeight: '300px',
										overflowY: 'auto',
										border: '1px solid #e1e5e9',
										borderRadius: '8px',
										padding: '16px',
									} }
								>
									<VStack spacing="3">
										{ getAvailableSitesForUser( selectedUser )?.map( ( site, index ) => {
											const isSelected = selectedSitesToAdd?.some( ( s ) => s.siteUrl === site.siteUrl );
											const selectedSite = selectedSitesToAdd?.find( ( s ) => s.siteUrl === site.siteUrl );

											return (
												<div
													key={ index }
													style={ {
														padding: '12px',
														border: '1px solid #f0f0f1',
														borderRadius: '4px',
													} }
												>
													<CheckboxControl
														label={
															<div>
																<div style={ { fontWeight: '500', color: '#23282d' } }>
																	{ site.siteName }
																</div>
																<div style={ { fontSize: '12px', color: '#6c757d' } }>
																	{ site.siteUrl }
																</div>
															</div>
														}
														checked={ isSelected }
														onChange={ () => {
															if ( isSelected ) {
																setSelectedSitesToAdd( ( prev ) =>
																	prev.filter( ( s ) => s.siteUrl !== site.siteUrl ),
																);
															} else {
																setSelectedSitesToAdd( ( prev ) => [
																	...prev,
																	{
																		siteUrl: site.siteUrl,
																		siteName: site.siteName,
																		apiKey: site.apiKey,
																		role: 'subscriber',
																	},
																] );
															}
														} }
													/>

													{ isSelected && (
														<div style={ { marginTop: '8px', marginLeft: '24px' } }>
															<SelectControl
																label={ __( 'Role', 'oneaccess' ) }
																value={ selectedSite?.role || 'subscriber' }
																options={ Object.entries( AVAILABLE_ROLES )?.map( ( [ role, label ] ) => ( {
																	value: role,
																	label,
																} ) ) }
																onChange={ ( value ) => {
																	setSelectedSitesToAdd( ( prev ) =>
																		prev?.map( ( s ) =>
																			s.siteUrl === site.siteUrl
																				? { ...s, role: value }
																				: s,
																		),
																	);
																} }
															/>
														</div>
													) }
												</div>
											);
										} ) }
									</VStack>
								</div>
							</>
						) : (
							<Notice status="warning" isDismissible={ false }>
								<p style={ { margin: 0 } }>
									{ __( 'This user is already assigned to all available sites.', 'oneaccess' ) }
								</p>
							</Notice>
						) }

						<HStack justify="flex-end" spacing="3">
							<Button
								variant="secondary"
								onClick={ () => {
									setShowAddToSitesModal( false );
									setSelectedSitesToAdd( [] );
								} }
							>
								{ __( 'Cancel', 'oneaccess' ) }
							</Button>
							<Button
								variant="primary"
								onClick={ handleAddUserToSites }
								disabled={ selectedSitesToAdd.length === 0 || isAddingToSites }
								isBusy={ isAddingToSites }
							>
								<Icon icon={ plus } />
								{ isAddingToSites ? __( 'Adding to Sites…', 'oneaccess' ) : __( 'Add to Sites', 'oneaccess' ) }
							</Button>
						</HStack>
					</VStack>
				</Modal>
			) }

			{ /* User Deletion Modal */ }
			{ showUserDeletionModal && selectedUser && (
				<Modal
					title={ __( 'Delete User', 'oneaccess' ) }
					onRequestClose={ () => setShowUserDeletionModal( false ) }
					shouldCloseOnClickOutside={ true }
					size="medium"
				>
					<VStack spacing="4">
						<div>
							<p style={ { margin: 0, color: '#6c757d', fontSize: '14px' } }>
								{ __( 'Delete User from selected sites: ', 'oneaccess' ) }
								<strong>{ selectedUser.full_name || selectedUser.username }</strong> ({ selectedUser.email })
							</p>
						</div>

						{ selectedUser?.sites?.length > 0 ? (
							<>
								<HStack justify="flex-start" spacing="3">
									<CheckboxControl
										label={ __( 'Select All Sites', 'oneaccess' ) }
										checked={ selectedSitesToDeleteUser.length === selectedUser?.sites?.length }
										onChange={ () => {
											const availableSitesToDeleteUser = selectedUser?.sites || [];
											if ( selectedSitesToDeleteUser.length === availableSitesToDeleteUser.length ) {
												setSelectedSitesToDeleteUser( [] );
											} else {
												setSelectedSitesToDeleteUser(
													availableSitesToDeleteUser?.map( ( site ) => ( {
														site_url: site.site_url,
														site_name: site.site_name,
													} ) ),
												);
											}
										} }
									/>
									<Button
										variant="link"
										onClick={ () => setSelectedSitesToDeleteUser( [] ) }
										disabled={ selectedSitesToDeleteUser.length === 0 }
										style={ { marginBlockEnd: '0.5rem' } }
									>
										{ __( 'Clear Selection', 'oneaccess' ) }
									</Button>
								</HStack>

								<div
									style={ {
										maxHeight: '300px',
										overflowY: 'auto',
										border: '1px solid #e1e5e9',
										borderRadius: '8px',
										padding: '16px',
									} }
								>
									<VStack spacing="3">
										{ selectedUser?.sites?.map( ( site, index ) => {
											const isSelected = selectedSitesToDeleteUser.some( ( s ) => s.site_url === site.site_url );
											return (
												<div
													key={ index }
													style={ {
														padding: '12px',
														border: '1px solid #f0f0f1',
														borderRadius: '4px',
														cursor: 'pointer',
													} }
													onClick={ () => {
														const isSelectedUser = selectedSitesToDeleteUser.some( ( s ) => s.site_url === site.site_url );
														if ( isSelectedUser ) {
															setSelectedSitesToDeleteUser( ( prev ) =>
																prev.filter( ( s ) => s.site_url !== site.site_url ),
															);
														} else {
															setSelectedSitesToDeleteUser( ( prev ) => [
																...prev,
																{
																	site_url: site.site_url,
																	site_name: site.site_name,
																},
															] );
														}
													} }
													role="button"
													tabIndex={ 0 }
													onKeyDown={ ( e ) => {
														if ( e.key === 'Enter' || e.key === ' ' ) {
															const isSelectedUser = selectedSitesToDeleteUser.some( ( s ) => s.site_url === site.site_url );
															if ( isSelectedUser ) {
																setSelectedSitesToDeleteUser( ( prev ) =>
																	prev.filter( ( s ) => s.site_url !== site.site_url ),
																);
															} else {
																setSelectedSitesToDeleteUser( ( prev ) => [
																	...prev,
																	{
																		site_url: site.site_url,
																		site_name: site.site_name,
																	},
																] );
															}
														}
													} }
												>
													<CheckboxControl
														className="oneaccess-site-checkbox"
														label={
															<div>
																<div style={ { fontWeight: '500', color: '#23282d' } }>
																	{ site.site_name }
																</div>
																<div style={ { fontSize: '12px', color: '#6c757d' } }>
																	{ site.site_url }
																</div>
															</div>
														}
														checked={ isSelected }
													/>
												</div>
											);
										} ) }
									</VStack>
								</div>
							</>
						) : (
							<Notice status="warning" isDismissible={ false }>
								<p style={ { margin: 0 } }>
									{ __( 'This user cannot be deleted.', 'oneaccess' ) }
								</p>
							</Notice>
						) }

						<HStack justify="flex-end" spacing="3">
							<Button
								variant="secondary"
								onClick={ () => {
									setShowUserDeletionModal( false );
									setSelectedSitesToDeleteUser( [] );
								} }
							>
								{ __( 'Cancel', 'oneaccess' ) }
							</Button>
							<Button
								variant="primary"
								isDestructive
								onClick={ handleUserDeletionFromSelectedSites }
								disabled={ selectedSitesToDeleteUser.length === 0 || isDeletingUser }
								isBusy={ isDeletingUser }
							>
								<Icon icon={ trash } />
								{ isDeletingUser ? __( 'Deleting user…', 'oneaccess' ) : __( 'Delete user', 'oneaccess' ) }
							</Button>
						</HStack>
					</VStack>
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

export default SharedUsers;
