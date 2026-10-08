<?php
switch ('users'):
case 'users':

                if (!isAdmin() && !hasRole('hr')) {
                    echo '<div class="alert alert-danger">⛔ Access Denied. Admin or HR only.</div>';
                    break;
                }
                $canManageUsers = isAdmin();
                $users = $userManager->getAllUsers();
                ?>
                
                <!-- ============================================ -->
                <!-- USERS PAGE - MODERN INTERFACE -->
                <!-- ============================================ -->
                
                <!-- Page Header -->
                <div class="usr-page-header">
                    <div class="usr-page-header-left">
                        <h2 class="usr-page-title">
                            <span class="usr-page-icon">👥</span>
                            User Management
                        </h2>
                        <p class="usr-page-subtitle">Manage system users and their access roles</p>
                    </div>
                    <?php if ($canManageUsers): ?>
                    <button class="usr-btn-primary no-print" onclick="showAddUser()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add User
                    </button>
                    <?php endif; ?>
                    <?php if (isAdmin() || hasRole('hr')): ?>
                    <button class="usr-btn-primary no-print" type="button" onclick="editCustomRole()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Add Role
                    </button>
                    <?php endif; ?>
                </div>
                
                <!-- Users Card -->
                <div class="usr-card">
                    
                    <!-- Card Header -->
                    <div class="usr-card-header">
                        <div class="usr-card-title-wrap">
                            <div class="usr-card-title">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                </svg>
                                All Users
                            </div>
                            <div class="usr-card-subtitle">
                                <span><?php echo count($users); ?></span> <?php echo count($users) === 1 ? 'user' : 'users'; ?> in system
                            </div>
                        </div>
                    </div>
                    
                    <!-- Users Table -->
                    <?php if (!empty($users)): ?>
                    <div class="usr-table-wrap">
                        <table class="usr-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Last Activity</th>
                                    <th class="no-print" style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                <tr>
                                    <td>
                                        <div class="usr-name-cell">
                                            <div class="usr-avatar <?php echo htmlspecialchars(getUserRoles($u['role'])[0] ?? ''); ?>">
                                                <?php echo strtoupper(substr($u['full_name'] ?? $u['username'], 0, 1)); ?>
                                            </div>
                                            <div class="usr-name-info">
                                                <div class="usr-fullname"><?php echo htmlspecialchars($u['full_name']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="usr-username">@<?php echo htmlspecialchars($u['username']); ?></span>
                                    </td>
                                    <td>
                                        <?php 
                                        $roleClasses = [
                                            'admin' => 'usr-role-admin',
                                            'cashier' => 'usr-role-cashier',
                                            'inventory' => 'usr-role-inventory',
                                            'hr' => 'usr-role-hr',
                                            'finance' => 'usr-role-finance'
                                        ];
                                        $primaryRole = getUserRoles($u['role'])[0] ?? '';
                                        $roleClass = $roleClasses[$primaryRole] ?? 'usr-role-default';
                                        ?>
                                        <span class="usr-role-badge <?php echo $roleClass; ?>">
                                            <?php echo htmlspecialchars(getRoleLabel($u['role'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($u['last_activity']): ?>
                                            <span class="usr-activity-date"><?php echo date('M d, Y', strtotime($u['last_activity'])); ?></span>
                                            <span class="usr-activity-time"><?php echo date('h:i A', strtotime($u['last_activity'])); ?></span>
                                        <?php else: ?>
                                            <span class="usr-empty">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print" style="text-align:right;">
                                        <div class="usr-actions">
                                            <?php if ($u['id'] != 1): ?>
                                                <?php if ($canManageUsers || (hasRole('hr') && !in_array('admin', getUserRoles($u['role']), true))): ?>
                                                <button type="button" class="usr-action-btn" onclick="editUserRoles(this)" data-user-id="<?php echo (int)$u['id']; ?>" data-roles="<?php echo htmlspecialchars(json_encode(getUserRoles($u['role'])), ENT_QUOTES, 'UTF-8'); ?>" title="Edit roles">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                        <path d="M12 20h9"></path>
                                                        <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L8 18l-4 1 1-4Z"></path>
                                                    </svg>
                                                </button>
                                                <?php else: ?>
                                                <span class="usr-protected-badge">🔒 Admin</span>
                                                <?php endif; ?>
                                                <?php if ($canManageUsers): ?>
                                                <button class="usr-action-btn usr-action-delete" onclick="deleteUser(<?php echo $u['id']; ?>)" title="Delete User">
                                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="3 6 5 6 21 6"></polyline>
                                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                                    </svg>
                                                </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="usr-protected-badge">🔒 Protected</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="usr-empty-state">
                        <div class="usr-empty-icon">👥</div>
                        <div class="usr-empty-title">No users yet</div>
                        <div class="usr-empty-text">Click "Add User" to create your first user</div>
                    </div>
                    <?php endif; ?>
                    
                </div>
                
                <!-- ============================================ -->
                <!-- ADD USER MODAL -->
                <!-- ============================================ -->
                <div class="modal" id="addUserModal">
                    <div class="modal-content usr-modal">
                        
                        <div class="usr-modal-header">
                            <div class="usr-modal-header-icon">
                                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="8.5" cy="7" r="4"></circle>
                                    <line x1="20" y1="8" x2="20" y2="14"></line>
                                    <line x1="23" y1="11" x2="17" y2="11"></line>
                                </svg>
                            </div>
                            <div class="usr-modal-header-text">
                                <h2>Add New User</h2>
                                <p>Create a new system user account</p>
                            </div>
                            <button class="usr-modal-close" onclick="closeModal('addUserModal')">&times;</button>
                        </div>
                        
                        <form id="userForm" onsubmit="saveUser(event)">
                            
                            <div class="usr-form-row">
                                <div class="usr-form-group">
                                    <label class="usr-form-label">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                            <circle cx="12" cy="7" r="4"></circle>
                                        </svg>
                                        Username *
                                    </label>
                                    <input type="text" id="userUsername" placeholder="e.g., jdoe" required class="usr-form-input">
                                </div>
                                <div class="usr-form-group">
                                    <label class="usr-form-label">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                        </svg>
                                        Password *
                                    </label>
                                    <input type="password" id="userPassword" placeholder="Enter password" required class="usr-form-input">
                                </div>
                            </div>
                            
                            <div class="usr-form-group">
                                <label class="usr-form-label">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                    Full Name *
                                </label>
                                <input type="text" id="userFullName" placeholder="e.g., John Doe" required class="usr-form-input">
                            </div>
                            
                            <div class="usr-form-group">
                                <label class="usr-form-label">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    </svg>
                                    Role
                                </label>
                                <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;">
                                    <?php if ($canManageUsers): ?>
                                    <label><input type="checkbox" name="userRoles" value="admin"> Admin</label>
                                    <?php endif; ?>
                                    <label><input type="checkbox" name="userRoles" value="cashier" checked> Cashier</label>
                                    <label><input type="checkbox" name="userRoles" value="inventory"> Inventory</label>
                                    <label><input type="checkbox" name="userRoles" value="hr"> HR</label>
                                    <label><input type="checkbox" name="userRoles" value="finance"> Finance</label>
                                    <?php foreach (getCustomRoles() as $customRole): ?>
                                    <label><input type="checkbox" name="userRoles" value="<?php echo htmlspecialchars($customRole['role_key'], ENT_QUOTES, 'UTF-8'); ?>"> <?php echo htmlspecialchars($customRole['role_name']); ?></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="usr-modal-actions">
                                <button type="button" class="usr-btn-cancel" onclick="closeModal('addUserModal')">
                                    Cancel
                                </button>
                                <button type="submit" class="usr-btn-submit">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                    Add User
                                </button>
                            </div>
                            
                        </form>
                    </div>
                </div>

                <div class="modal" id="editUserRolesModal">
                    <div class="modal-content usr-modal">
                        <div class="usr-modal-header">
                            <div class="usr-modal-header-icon">👥</div>
                            <div class="usr-modal-header-text">
                                <h2>Edit User Roles</h2>
                                <p>Select the access this user should have</p>
                            </div>
                            <button class="usr-modal-close" type="button" onclick="closeModal('editUserRolesModal')">&times;</button>
                        </div>
                        <form onsubmit="saveUserRoles(event)">
                            <input type="hidden" id="editRolesUserId">
                            <div id="editUserRoleOptions" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;">
                                <?php if ($canManageUsers): ?>
                                <label><input type="checkbox" value="admin"> Admin</label>
                                <?php endif; ?>
                                <label><input type="checkbox" value="cashier"> Cashier</label>
                                <label><input type="checkbox" value="inventory"> Inventory</label>
                                <label><input type="checkbox" value="hr"> HR</label>
                                <label><input type="checkbox" value="finance"> Finance</label>
                                <?php foreach (getCustomRoles() as $customRole): ?>
                                <label><input type="checkbox" value="<?php echo htmlspecialchars($customRole['role_key'], ENT_QUOTES, 'UTF-8'); ?>"> <?php echo htmlspecialchars($customRole['role_name']); ?></label>
                                <?php endforeach; ?>
                            </div>
                            <div class="usr-modal-actions">
                                <button type="button" class="usr-btn-cancel" onclick="closeModal('editUserRolesModal')">Cancel</button>
                                <button type="submit" class="usr-btn-submit">Save Roles</button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (isAdmin() || hasRole('hr')): ?>
                <div class="modal" id="roleManagerModal">
                    <div class="modal-content usr-modal">
                        <div class="usr-modal-header">
                            <div class="usr-modal-header-icon">🛡️</div>
                            <div class="usr-modal-header-text">
                                <h2>Manage Roles</h2>
                                <p>Built-in roles stay fixed; custom roles can be edited below.</p>
                            </div>
                            <button class="usr-modal-close" type="button" onclick="closeModal('roleManagerModal')">&times;</button>
                        </div>
                        <div style="display:flex;justify-content:flex-end;margin-bottom:12px;">
                            <button class="usr-btn-submit" type="button" onclick="editCustomRole()">Add Role</button>
                        </div>
                        <div style="display:grid;gap:8px;">
                            <?php foreach (getCustomRoles() as $customRole): ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 12px;border:1px solid #e5e7eb;border-radius:6px;">
                                <div><strong><?php echo htmlspecialchars($customRole['role_name']); ?></strong><div style="font-size:12px;color:#64748b;"><?php echo count($customRole['permissions']); ?> permissions</div></div>
                                <?php if (isAdmin() || hasRole('hr')): ?>
                                <div style="display:flex;gap:8px;">
                                    <button class="usr-btn-cancel" type="button" onclick="editCustomRole(this)" data-role-key="<?php echo htmlspecialchars($customRole['role_key'], ENT_QUOTES, 'UTF-8'); ?>" data-role-name="<?php echo htmlspecialchars($customRole['role_name'], ENT_QUOTES, 'UTF-8'); ?>" data-permissions="<?php echo htmlspecialchars(json_encode($customRole['permissions']), ENT_QUOTES, 'UTF-8'); ?>">Edit</button>
                                    <button class="usr-btn-cancel" type="button" onclick="deleteCustomRole('<?php echo htmlspecialchars($customRole['role_key'], ENT_QUOTES, 'UTF-8'); ?>')">Delete</button>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty(getCustomRoles())): ?>
                            <div class="usr-empty-text">No custom roles yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="modal" id="customRoleEditorModal">
                    <div class="modal-content usr-modal">
                        <div class="usr-modal-header">
                            <div class="usr-modal-header-icon">🛡️</div>
                            <div class="usr-modal-header-text">
                                <h2 id="customRoleEditorTitle">Add Role</h2>
                                <p>Choose a name and the pages this role can access.</p>
                            </div>
                            <button class="usr-modal-close" type="button" onclick="closeModal('customRoleEditorModal')">&times;</button>
                        </div>
                        <form onsubmit="saveCustomRole(event)">
                            <input type="hidden" id="customRoleKey">
                            <div class="usr-form-group">
                                <label class="usr-form-label" for="customRoleName">Role name</label>
                                <input class="usr-form-input" id="customRoleName" maxlength="80" required>
                            </div>
                            <div class="usr-form-group">
                                <span class="usr-form-label">Page access</span>
                                <p class="role-permission-help">Choose the sections and actions this role should be allowed to use.</p>
                                <?php
                                $rolePermissionOptions = getRolePermissionOptions();
                                $rolePermissionGroups = [
                                    'Sales & customers' => ['dashboard', 'cart', 'products', 'categories', 'archive', 'customers', 'sales', 'reports', 'customer_reports'],
                                    'Inventory' => ['stock', 'stock_adjust', 'stock_history', 'purchases', 'purchases_create', 'purchases_view', 'suppliers', 'suppliers_create', 'suppliers_edit', 'inventory_reports'],
                                    'Finance' => ['finance_dashboard', 'finance_reports', 'finance'],
                                    'Returns' => ['returns', 'returns_create', 'returns_approve', 'returns_settings', 'returns_view', 'returns_reports'],
                                    'People & system' => ['users', 'activity', 'settings', 'hr']
                                ];
                                $renderedPermissionKeys = [];
                                ?>
                                <div class="role-permission-list">
                                    <?php foreach ($rolePermissionGroups as $groupName => $permissionKeys): ?>
                                    <fieldset class="role-permission-group">
                                        <legend><?php echo htmlspecialchars($groupName); ?></legend>
                                        <div class="role-permission-options">
                                            <?php foreach ($permissionKeys as $permissionKey): ?>
                                                <?php if (!isset($rolePermissionOptions[$permissionKey])) continue; ?>
                                                <?php $renderedPermissionKeys[] = $permissionKey; ?>
                                                <label>
                                                    <input type="checkbox" name="customRolePermissions" value="<?php echo htmlspecialchars($permissionKey, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <span><?php echo htmlspecialchars($rolePermissionOptions[$permissionKey]); ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                    <?php endforeach; ?>
                                    <?php
                                    $unassignedPermissions = array_diff_key($rolePermissionOptions, array_flip($renderedPermissionKeys));
                                    if (!empty($unassignedPermissions)):
                                    ?>
                                    <fieldset class="role-permission-group">
                                        <legend>Other access</legend>
                                        <div class="role-permission-options">
                                            <?php foreach ($unassignedPermissions as $permissionKey => $permissionLabel): ?>
                                            <label>
                                                <input type="checkbox" name="customRolePermissions" value="<?php echo htmlspecialchars($permissionKey, ENT_QUOTES, 'UTF-8'); ?>">
                                                <span><?php echo htmlspecialchars($permissionLabel); ?></span>
                                            </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="usr-modal-actions">
                                <button type="button" class="usr-btn-cancel" onclick="closeModal('customRoleEditorModal')">Cancel</button>
                                <button type="submit" class="usr-btn-submit">Save Role</button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
                
                <script>
                    // ===== USERS JAVASCRIPT (Functions preserved) =====
                    
                    function showAddUser(){ 
                        document.getElementById('userForm').reset();
                        document.getElementById('addUserModal').classList.add('show'); 
                    }
                    
                    function saveUser(e){ 
                        e.preventDefault(); 
                        var data = new FormData(); 
                        data.append('username', document.getElementById('userUsername').value); 
                        data.append('password', document.getElementById('userPassword').value); 
                        data.append('full_name', document.getElementById('userFullName').value); 
                        document.querySelectorAll('input[name="userRoles"]:checked').forEach(function(input) {
                            data.append('role[]', input.value);
                        });
                        
                        fetch('?action=add_user', { method: 'POST', body: data })
                        .then(function(res) { return res.json(); })
                        .then(function(result){ 
                            if(result.success){ 
                                alert('✅ User added successfully!'); 
                                closeModal('addUserModal'); 
                                location.reload(); 
                            } else { 
                                alert('❌ Error: ' + result.message); 
                            } 
                        }); 
                    }

                    function editUserRoles(button) {
                        var selectedRoles = JSON.parse(button.dataset.roles || '[]');
                        document.getElementById('editRolesUserId').value = button.dataset.userId;
                        document.querySelectorAll('#editUserRoleOptions input[type="checkbox"]').forEach(function(input) {
                            input.checked = selectedRoles.indexOf(input.value) !== -1;
                        });
                        document.getElementById('editUserRolesModal').classList.add('show');
                    }

                    function saveUserRoles(e) {
                        e.preventDefault();
                        var data = new FormData();
                        data.append('id', document.getElementById('editRolesUserId').value);
                        document.querySelectorAll('#editUserRoleOptions input[type="checkbox"]:checked').forEach(function(input) {
                            data.append('roles[]', input.value);
                        });
                        fetch('?action=update_user_roles', { method: 'POST', body: data })
                        .then(function(res) { return res.json(); })
                        .then(function(result) {
                            if (result.success) {
                                location.reload();
                            } else {
                                alert(result.message || 'Unable to update roles.');
                            }
                        });
                    }
                    
                    function deleteUser(id){ 
                        customConfirm('Delete this user permanently? This action cannot be undone.', function() {
                            fetch('?action=delete_user&id=' + id)
                            .then(function(res) { return res.json(); })
                            .then(function(result){ 
                                if(result.success){ 
                                    alert('✅ User deleted!'); 
                                    location.reload(); 
                                } 
                            }); 
                        }, 'danger');
                    }
                    
                    function closeModal(id){ document.getElementById(id).classList.remove('show'); }

                    function showRoleManager() {
                        document.getElementById('roleManagerModal').classList.add('show');
                    }

                    function editCustomRole(button) {
                        document.getElementById('customRoleEditorTitle').textContent = button ? 'Edit Role' : 'Add Role';
                        document.getElementById('customRoleKey').value = button ? button.dataset.roleKey : '';
                        document.getElementById('customRoleName').value = button ? button.dataset.roleName : '';
                        var selectedPermissions = button ? JSON.parse(button.dataset.permissions || '[]') : ['dashboard'];
                        document.querySelectorAll('input[name="customRolePermissions"]').forEach(function(input) {
                            input.checked = selectedPermissions.indexOf(input.value) !== -1;
                        });
                        document.getElementById('customRoleEditorModal').classList.add('show');
                    }

                    function saveCustomRole(e) {
                        e.preventDefault();
                        var data = new FormData();
                        data.append('role_key', document.getElementById('customRoleKey').value);
                        data.append('role_name', document.getElementById('customRoleName').value);
                        document.querySelectorAll('input[name="customRolePermissions"]:checked').forEach(function(input) {
                            data.append('permissions[]', input.value);
                        });
                        fetch('?action=save_custom_role', { method: 'POST', body: data })
                        .then(function(res) { return res.json(); })
                        .then(function(result) {
                            if (result.success) {
                                location.href = '?page=users&manage_roles=1';
                            } else {
                                alert(result.message || 'Unable to save this role.');
                            }
                        });
                    }

                    function deleteCustomRole(roleKey) {
                        if (!window.confirm('Delete this custom role?')) return;
                        var data = new FormData();
                        data.append('role_key', roleKey);
                        fetch('?action=delete_custom_role', { method: 'POST', body: data })
                        .then(function(res) { return res.json(); })
                        .then(function(result) {
                            if (result.success) {
                                location.href = '?page=users&manage_roles=1';
                            } else {
                                alert(result.message || 'Unable to delete this role.');
                            }
                        });
                    }

                    if (new URLSearchParams(window.location.search).get('manage_roles') === '1') {
                        showRoleManager();
                    }
                    if (new URLSearchParams(window.location.search).get('add_role') === '1') {
                        editCustomRole();
                    }
                </script>
                
                <?php
break;
endswitch;
