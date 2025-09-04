/* eslint-disable @wordpress/no-unsafe-wp-apis */
import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
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
const API_KEY = OneAccess.apiKey;
const AVAILABLE_ROLES = OneAccess.availableRoles || [];
const PER_PAGE = 20;
const PAGE = 1;

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
	const [ page, setPage ] = useState( PAGE );
	const [ totalPages, setTotalPages ] = useState( 1 );

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
			const response = await fetch(
				`${ API_NAMESPACE }/new-users?${ new Date().getTime().toString() }`,
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
					message: __( 'Failed to fetch users. Please try again later.', 'oneaccess' ),
				} );
				throw new Error( 'Failed to fetch users' );
			}

			const data = await response.json();
			setUsers( data.users || [] );
			setTotalPages( Math.ceil( ( data.count || 0 ) / PER_PAGE ) );
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to fetch users. Please try again later.', 'oneaccess' ),
			} );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		fetchUsers();
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Filter users based on search term and site filter
	const filteredUsers = users.filter( ( user ) => {
		const matchesSearch = user.username.toLowerCase().includes( searchTerm.toLowerCase() ) ||
                                user.email.toLowerCase().includes( searchTerm.toLowerCase() ) ||
                                user.full_name.toLowerCase().includes( searchTerm.toLowerCase() );

		const matchesSiteFilter = ! selectedSiteFilter ||
                user.sites.some( ( site ) => site.site_url === selectedSiteFilter );

		const matchesRoleFilter = ! selectedUserRole ||
				user.sites.some( ( site ) => site.role === selectedUserRole );

		return matchesSearch && matchesSiteFilter && matchesRoleFilter;
	} );

	// Get sites available for adding (sites user is not already assigned to)
	const getAvailableSitesForUser = ( user ) => {
		const userSiteUrls = user.sites?.map( ( site ) => site.site_url );
		return availableSites.filter( ( site ) => ! userSiteUrls.includes( site.siteUrl ) );
	};

	// Handle opening manage roles modal
	const handleManageRoles = ( user ) => {
		setSelectedUser( user );

		// Initialize user roles with current roles
		const initialRoles = {};
		availableSites.forEach( ( site ) => {
			const userSite = user.sites.find( ( s ) => s.site_url === site.siteUrl );
			if ( userSite ) {
				initialRoles[ site.siteUrl ] = userSite ? userSite.role : '';
			}
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
		// Implement user deletion logic here
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
						'X-OneAccess-Token': API_KEY,
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
	const handleUpdateRoles = async () => {
		setIsUpdatingRoles( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/update-user-roles`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
						'X-OneAccess-Token': API_KEY,
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
			fetchUsers();
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to update user roles. Please try again.', 'oneaccess' ),
			} );
		} finally {
			setIsUpdatingRoles( false );
			setShowManageRolesModal( false );
		}
	};

	// Handle adding user to sites
	const handleAddUserToSites = async () => {
		setIsAddingToSites( true );
		try {
			const response = await fetch(
				`${ API_NAMESPACE }/add-user-to-sites`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': NONCE,
						'X-OneAccess-Token': API_KEY,
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
			fetchUsers();
		} catch ( error ) {
			setNotice( {
				type: 'error',
				message: __( 'Failed to add user to sites. Please try again.', 'oneaccess' ),
			} );
		} finally {
			setIsAddingToSites( false );
			setShowAddToSitesModal( false );
		}
	};

	const filterUsersForPagination = useCallback( () => {
		const startIndex = ( page - 1 ) * PER_PAGE;
		const result = filteredUsers.slice( startIndex, startIndex + PER_PAGE );
		return result;
	}, [ filteredUsers, page ] );

	useEffect( () => {
		setTotalPages( Math.ceil( filteredUsers.length / PER_PAGE ) );
	}, [ filteredUsers ] );

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
							onChange={ setSearchTerm }
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

					<table className="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th>{ __( 'Name', 'oneaccess' ) }</th>
								<th>{ __( 'Email', 'oneaccess' ) }</th>
								<th>{ __( 'Sites', 'oneaccess' ) }</th>
								<th>{ __( 'Actions', 'oneaccess' ) }</th>
							</tr>
						</thead>
						<tbody>
							{
								isLoading ? (
									<tr>
										<td colSpan="4" style={ { textAlign: 'center' } }>
											<Spinner />
										</td>
									</tr>
								) : null
							}
							{ isLoading === false && filteredUsers.length === 0 && (
								<tr>
									<td colSpan="4" style={ { textAlign: 'center' } }>
										{ __( 'No users found.', 'oneaccess' ) }
									</td>
								</tr>
							) }
							{ filterUsersForPagination()?.map( ( user, index ) => (
								<tr key={ `${ user.username }-${ index }` }>
									<td>{ decodeEntities( user.full_name ?? user.username ) }</td>
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
											{ user.sites?.map( ( site, siteIndex ) => (

												<span
													key={ `${ site.site_url }-${ siteIndex }` }
													className="site-badge"
													style={ {
														display: 'inline-block',
														margin: '0',
														padding: '2px 8px',
														backgroundColor: '#007cba',
														color: '#fff',
														borderRadius: '4px',
														fontSize: '12px',
													} }
												>
													{ decodeEntities( site.site_name ) } ({ AVAILABLE_ROLES[ site.role ] || site.role })
												</span>
											) ) }
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
																disabled={ getAvailableSitesForUser( user ).length === 0 }
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
								<strong>{ selectedUser.username }</strong> ({ selectedUser.email })
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
												{ site.siteName ?? site.site_name }
											</div>
											<div style={ { fontSize: '12px', color: '#6c757d' } }>
												{ site.siteUrl ?? site.site_url }
											</div>
										</div>
										<SelectControl
											value={ userRoles[ site.site_url ] || '' }
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
								disabled={ isUpdatingRoles || selectedUser.sites.every( ( site ) => userRoles[ site.site_url ] === ( site.role || '' ) ) }
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
								<strong>{ selectedUser.username }</strong> ({ selectedUser.email })
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
																	},
																] );
															}
														} }
													/>

													<div style={ { marginTop: '8px', marginLeft: '24px' } }>
														<SelectControl
															disabled={ selectedSitesToAdd.some( ( s ) => s.siteUrl === site.siteUrl ) === false }
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
								<Icon
									icon={ plus }
								/>
								{ isAddingToSites ? __( 'Adding to Sites…', 'oneaccess' ) : __( 'Add to Sites', 'oneaccess' ) }
							</Button>
						</HStack>
					</VStack>
				</Modal>
			) }

			{ /* User Deletion Modal - Placeholder for future implementation */ }
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
								<strong>{ selectedUser.username }</strong> ({ selectedUser.email })
							</p>
						</div>

						{ selectedUser?.sites?.length > 0 ? (
							<>
								<HStack justify="flex-start" spacing="3">
									<CheckboxControl
										label={ __( 'Select All Sites', 'oneaccess' ) }
										checked={ selectedSitesToDeleteUser.length === selectedUser?.sites?.length }
										onChange={ () => {
											const availableSitesToAddUser = selectedUser?.sites || [];
											if ( selectedSitesToDeleteUser.length === availableSitesToAddUser.length ) {
												setSelectedSitesToDeleteUser( [] );
											} else {
												setSelectedSitesToDeleteUser(
													availableSitesToAddUser?.map( ( site ) => ( {
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
									{ __( 'This user can not be deleted.', 'oneaccess' ) }
								</p>
							</Notice>
						) }

						<HStack justify="flex-end" spacing="3">
							<Button
								variant="secondary"
								onClick={ () => {
									setSelectedSitesToDeleteUser( false );
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
								<Icon
									icon={ trash }
								/>
								{ __( 'Delete user', 'oneaccess' ) }
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
/* eslint-enable @wordpress/no-unsafe-wp-apis */
