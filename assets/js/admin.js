document.addEventListener('DOMContentLoaded', function() {
    const data = window.MemoriesAdminShieldData || {
        roles: {},
        roleCounts: {},
        discovered: {menus: {}, admin_bar: {}},
        settings: {roles: {}},
        bypass: 0,
        nonce: "",
        ajaxUrl: "",
        activeRole: 'administrator'
    };

    // Normalize settings to object (prevents empty array JSON stringify bug)
    if (Array.isArray(data.settings) || !data.settings) {
        data.settings = { roles: {} };
    } else if (!data.settings.roles) {
        data.settings.roles = {};
    }

    // Render roles sidebar list
    function renderRoleList() {
        const listContainer = document.getElementById('mas-role-list');
        if (!listContainer) return;
        listContainer.innerHTML = '';
        
        const roleKeys = Object.keys(data.roles);
        
        roleKeys.forEach(roleKey => {
            const roleName = data.roles[roleKey];
            const count = data.roleCounts[roleKey] || 0;
            
            const li = document.createElement('li');
            li.className = 'mas-role-item' + (roleKey === data.activeRole ? ' active' : '');
            li.dataset.role = roleKey;
            
            const nameSpan = document.createElement('span');
            nameSpan.textContent = roleName;
            li.appendChild(nameSpan);
            
            const badge = document.createElement('span');
            badge.className = 'mas-role-badge';
            badge.textContent = count;
            li.appendChild(badge);
            
            li.addEventListener('click', () => {
                document.querySelectorAll('.mas-role-item').forEach(item => item.classList.remove('active'));
                li.classList.add('active');
                data.activeRole = roleKey;
                
                // Update panel title
                const titleEl = document.getElementById('mas-active-role-title');
                if (titleEl) {
                    titleEl.textContent = `Configuring: ${roleName}`;
                }
                
                // Re-render views
                const searchInput = document.getElementById('mas-search-input');
                renderSidebarMenus(searchInput ? searchInput.value : '');
                renderAdminBarNodes(searchInput ? searchInput.value : '');
            });
            
            listContainer.appendChild(li);
        });
    }

    function getSetting(type, slug, parentSlug = null) {
        const roleSettings = data.settings.roles[data.activeRole] || {};
        
        if (type === 'menus') {
            const menus = roleSettings.menus || {};
            return !!menus[slug];
        } else if (type === 'submenus') {
            const submenus = roleSettings.submenus || {};
            const parentSubs = submenus[parentSlug] || {};
            return !!parentSubs[slug];
        } else if (type === 'admin_bar') {
            const adminBar = roleSettings.admin_bar || {};
            return !!adminBar[slug];
        }
        return false;
    }

    function updateSetting(type, slug, val, parentSlug = null) {
        if (!data.settings.roles[data.activeRole]) {
            data.settings.roles[data.activeRole] = {
                menus: {},
                submenus: {},
                admin_bar: {}
            };
        }
        
        const roleSettings = data.settings.roles[data.activeRole];
        
        if (type === 'menus') {
            if (!roleSettings.menus) roleSettings.menus = {};
            roleSettings.menus[slug] = val;
        } else if (type === 'submenus') {
            if (!roleSettings.submenus) roleSettings.submenus = {};
            if (!roleSettings.submenus[parentSlug]) roleSettings.submenus[parentSlug] = {};
            roleSettings.submenus[parentSlug][slug] = val;
        } else if (type === 'admin_bar') {
            if (!roleSettings.admin_bar) roleSettings.admin_bar = {};
            roleSettings.admin_bar[slug] = val;
        }
    }

    function renderSidebarMenus(searchQuery = '') {
        const treeContainer = document.getElementById('mas-sidebar-menu-tree');
        if (!treeContainer) return;
        treeContainer.innerHTML = '';
        
        const query = searchQuery.toLowerCase().trim();
        const menuKeys = Object.keys(data.discovered.menus || {});
        
        let renderedCount = 0;
        
        menuKeys.forEach(slug => {
            const menuData = data.discovered.menus[slug];
            const parentTitle = menuData.title || slug;
            const submenus = menuData.submenus || {};
            const submenuKeys = Object.keys(submenus);
            
            const parentMatches = parentTitle.toLowerCase().includes(query) || slug.toLowerCase().includes(query);
            
            const matchingSubmenuKeys = submenuKeys.filter(subSlug => {
                const subTitle = submenus[subSlug] || subSlug;
                return subTitle.toLowerCase().includes(query) || subSlug.toLowerCase().includes(query);
            });
            
            if (query && !parentMatches && matchingSubmenuKeys.length === 0) {
                return; 
            }
            
            renderedCount++;
            
            const parentDiv = document.createElement('div');
            parentDiv.className = 'mas-parent-item';
            
            const parentHeader = document.createElement('div');
            parentHeader.className = 'mas-parent-header mas-menu-row';
            
            const menuLabel = document.createElement('div');
            menuLabel.className = 'mas-menu-label';
            
            const titleSpan = document.createElement('span');
            titleSpan.textContent = parentTitle;
            menuLabel.appendChild(titleSpan);
            
            if (slug) {
                const slugSpan = document.createElement('span');
                slugSpan.className = 'mas-checkbox-label-meta';
                slugSpan.textContent = `(${slug})`;
                menuLabel.appendChild(slugSpan);
            }
            
            // Protect Admin settings lockout
            const isLockoutPrevented = (data.activeRole === 'administrator' && (slug === 'memories-admin-shield' || slug === 'client-safe-admin.php' || slug === 'client-safe-admin'));
            if (isLockoutPrevented) {
                const lockBadge = document.createElement('span');
                lockBadge.className = 'mas-badge-disabled-msg';
                lockBadge.textContent = 'Always Visible (Safety Lock)';
                menuLabel.appendChild(lockBadge);
            }
            parentHeader.appendChild(menuLabel);

            // Radio Group setup
            const radioGroup = document.createElement('div');
            radioGroup.className = 'mas-radio-group';
            
            const isHidden = getSetting('menus', slug);

            // Show option
            const showLabel = document.createElement('label');
            showLabel.className = 'mas-radio-label';
            const showRadio = document.createElement('input');
            showRadio.type = 'radio';
            showRadio.name = `menu_${slug}`;
            showRadio.value = 'show';
            showRadio.checked = !isHidden;
            if (isLockoutPrevented) {
                showRadio.disabled = true;
                showRadio.checked = true;
            }
            showLabel.appendChild(showRadio);
            showLabel.appendChild(document.createTextNode('Show'));

            // Hide option
            const hideLabel = document.createElement('label');
            hideLabel.className = 'mas-radio-label';
            const hideRadio = document.createElement('input');
            hideRadio.type = 'radio';
            hideRadio.name = `menu_${slug}`;
            hideRadio.value = 'hide';
            hideRadio.checked = isHidden;
            if (isLockoutPrevented) {
                hideRadio.disabled = true;
                hideRadio.checked = false;
                hideLabel.className += ' disabled';
            }
            hideLabel.appendChild(hideRadio);
            hideLabel.appendChild(document.createTextNode('Hide'));

            // Action Listeners
            showRadio.addEventListener('change', () => {
                updateSetting('menus', slug, false);
                const subList = parentDiv.querySelector('.mas-submenu-list');
                if (subList) {
                    subList.style.display = 'flex';
                }
            });

            hideRadio.addEventListener('change', () => {
                updateSetting('menus', slug, true);
                const subList = parentDiv.querySelector('.mas-submenu-list');
                if (subList) {
                    subList.style.display = 'none';
                }
            });

            radioGroup.appendChild(showLabel);
            radioGroup.appendChild(hideLabel);
            parentHeader.appendChild(radioGroup);
            parentDiv.appendChild(parentHeader);
            
            // Submenus
            if (submenuKeys.length > 0) {
                const submenuList = document.createElement('div');
                submenuList.className = 'mas-submenu-list';
                if (isHidden) {
                    submenuList.style.display = 'none';
                }
                
                submenuKeys.forEach(subSlug => {
                    if (query && !parentMatches && !matchingSubmenuKeys.includes(subSlug)) {
                        return;
                    }
                    
                    const subTitle = submenus[subSlug] || subSlug;
                    const subRow = document.createElement('div');
                    subRow.className = 'mas-menu-row';
                    
                    const subLabel = document.createElement('div');
                    subLabel.className = 'mas-menu-label';
                    
                    const subTitleSpan = document.createElement('span');
                    subTitleSpan.textContent = subTitle;
                    subLabel.appendChild(subTitleSpan);
                    
                    if (subSlug) {
                        const subSlugSpan = document.createElement('span');
                        subSlugSpan.className = 'mas-checkbox-label-meta';
                        subSlugSpan.textContent = `(${subSlug})`;
                        subLabel.appendChild(subSlugSpan);
                    }
                    
                    const isSubLockoutPrevented = (data.activeRole === 'administrator' && (subSlug === 'memories-admin-shield' || subSlug === 'client-safe-admin.php' || subSlug === 'client-safe-admin'));
                    if (isSubLockoutPrevented) {
                        const lockBadge = document.createElement('span');
                        lockBadge.className = 'mas-badge-disabled-msg';
                        lockBadge.textContent = 'Always Visible (Safety Lock)';
                        subLabel.appendChild(lockBadge);
                    }
                    subRow.appendChild(subLabel);

                    // Submenu Radio choices
                    const subRadioGroup = document.createElement('div');
                    subRadioGroup.className = 'mas-radio-group';
                    
                    const isSubHidden = getSetting('submenus', subSlug, slug);

                    // Show Option
                    const subShowLabel = document.createElement('label');
                    subShowLabel.className = 'mas-radio-label';
                    const subShowRadio = document.createElement('input');
                    subShowRadio.type = 'radio';
                    subShowRadio.name = `submenu_${slug}_${subSlug}`;
                    subShowRadio.value = 'show';
                    subShowRadio.checked = !isSubHidden;
                    if (isSubLockoutPrevented) {
                        subShowRadio.disabled = true;
                        subShowRadio.checked = true;
                    }
                    subShowLabel.appendChild(subShowRadio);
                    subShowLabel.appendChild(document.createTextNode('Show'));

                    // Hide Option
                    const subHideLabel = document.createElement('label');
                    subHideLabel.className = 'mas-radio-label';
                    const subHideRadio = document.createElement('input');
                    subHideRadio.type = 'radio';
                    subHideRadio.name = `submenu_${slug}_${subSlug}`;
                    subHideRadio.value = 'hide';
                    subHideRadio.checked = isSubHidden;
                    if (isSubLockoutPrevented) {
                        subHideRadio.disabled = true;
                        subHideRadio.checked = false;
                        subHideLabel.className += ' disabled';
                    }
                    subHideLabel.appendChild(subHideRadio);
                    subHideLabel.appendChild(document.createTextNode('Hide'));

                    subShowRadio.addEventListener('change', () => {
                        updateSetting('submenus', subSlug, false, slug);
                    });

                    subHideRadio.addEventListener('change', () => {
                        updateSetting('submenus', subSlug, true, slug);
                    });

                    subRadioGroup.appendChild(subShowLabel);
                    subRadioGroup.appendChild(subHideLabel);
                    subRow.appendChild(subRadioGroup);
                    submenuList.appendChild(subRow);
                });
                
                if (submenuList.children.length > 0) {
                    parentDiv.appendChild(submenuList);
                }
            }
            
            treeContainer.appendChild(parentDiv);
        });
        
        if (renderedCount === 0) {
            treeContainer.innerHTML = '<div style="color: #64748b; text-align: center; padding: 20px;">No sidebar menus found matching search.</div>';
        }
    }

    function renderAdminBarNodes(searchQuery = '') {
        const listContainer = document.getElementById('mas-adminbar-nodes-list');
        if (!listContainer) return;
        listContainer.innerHTML = '';
        
        const query = searchQuery.toLowerCase().trim();
        const nodeKeys = Object.keys(data.discovered.admin_bar || {});
        
        let renderedCount = 0;
        
        nodeKeys.forEach(id => {
            const title = data.discovered.admin_bar[id] || id;
            
            if (query && !title.toLowerCase().includes(query) && !id.toLowerCase().includes(query)) {
                return;
            }
            
            renderedCount++;
            
            const nodeDiv = document.createElement('div');
            nodeDiv.className = 'mas-node-item mas-menu-row';
            
            const nodeLabel = document.createElement('div');
            nodeLabel.className = 'mas-menu-label';
            
            const titleSpan = document.createElement('span');
            titleSpan.textContent = title;
            nodeLabel.appendChild(titleSpan);
            
            const idSpan = document.createElement('span');
            idSpan.className = 'mas-checkbox-label-meta';
            idSpan.textContent = `(${id})`;
            nodeLabel.appendChild(idSpan);
            nodeDiv.appendChild(nodeLabel);
            
            // Radio Group
            const radioGroup = document.createElement('div');
            radioGroup.className = 'mas-radio-group';
            
            const isNodeHidden = getSetting('admin_bar', id);

            // Show Option
            const showLabel = document.createElement('label');
            showLabel.className = 'mas-radio-label';
            const showRadio = document.createElement('input');
            showRadio.type = 'radio';
            showRadio.name = `adminbar_${id}`;
            showRadio.value = 'show';
            showRadio.checked = !isNodeHidden;
            showLabel.appendChild(showRadio);
            showLabel.appendChild(document.createTextNode('Show'));

            // Hide Option
            const hideLabel = document.createElement('label');
            hideLabel.className = 'mas-radio-label';
            const hideRadio = document.createElement('input');
            hideRadio.type = 'radio';
            hideRadio.name = `adminbar_${id}`;
            hideRadio.value = 'hide';
            hideRadio.checked = isNodeHidden;
            hideLabel.appendChild(hideRadio);
            hideLabel.appendChild(document.createTextNode('Hide'));

            showRadio.addEventListener('change', () => {
                updateSetting('admin_bar', id, false);
            });

            hideRadio.addEventListener('change', () => {
                updateSetting('admin_bar', id, true);
            });
            
            radioGroup.appendChild(showLabel);
            radioGroup.appendChild(hideLabel);
            nodeDiv.appendChild(radioGroup);
            
            listContainer.appendChild(nodeDiv);
        });
        
        if (renderedCount === 0) {
            listContainer.innerHTML = '<div style="color: #64748b; text-align: center; padding: 20px;">No Admin Bar nodes found matching search.</div>';
        }
    }

    // Initialize layout and views
    const activeRoleTitleEl = document.getElementById('mas-active-role-title');
    if (activeRoleTitleEl) {
        activeRoleTitleEl.textContent = `Configuring: ${data.roles[data.activeRole]}`;
    }
    renderRoleList();
    renderSidebarMenus();
    renderAdminBarNodes();
    updateBypassUI();

    // Search Box Listener
    const searchInput = document.getElementById('mas-search-input');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            renderSidebarMenus(e.target.value);
            renderAdminBarNodes(e.target.value);
        });
    }

    // Tabs navigation listener
    document.querySelectorAll('.mas-tab-nav-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('.mas-tab-nav-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.mas-tab-content').forEach(c => c.classList.remove('active'));
            
            btn.classList.add('active');
            const tabId = btn.dataset.tab;
            const contentEl = document.getElementById(`mas-tab-${tabId}`);
            if (contentEl) {
                contentEl.classList.add('active');
            }
        });
    });

    // Bulk selection handlers
    const selectAllSidebarBtn = document.getElementById('mas-select-all-sidebar');
    if (selectAllSidebarBtn) {
        selectAllSidebarBtn.addEventListener('click', () => {
            toggleAllRadiosInView('sidebar', 'hide');
        });
    }

    const deselectAllSidebarBtn = document.getElementById('mas-deselect-all-sidebar');
    if (deselectAllSidebarBtn) {
        deselectAllSidebarBtn.addEventListener('click', () => {
            toggleAllRadiosInView('sidebar', 'show');
        });
    }

    const selectAllAdminbarBtn = document.getElementById('mas-select-all-adminbar');
    if (selectAllAdminbarBtn) {
        selectAllAdminbarBtn.addEventListener('click', () => {
            toggleAllRadiosInView('adminbar', 'hide');
        });
    }

    const deselectAllAdminbarBtn = document.getElementById('mas-deselect-all-adminbar');
    if (deselectAllAdminbarBtn) {
        deselectAllAdminbarBtn.addEventListener('click', () => {
            toggleAllRadiosInView('adminbar', 'show');
        });
    }

    function toggleAllRadiosInView(tabType, actionType) {
        const selector = tabType === 'sidebar' 
            ? `#mas-sidebar-menu-tree input[type="radio"][value="${actionType}"]:not(:disabled)`
            : `#mas-adminbar-nodes-list input[type="radio"][value="${actionType}"]:not(:disabled)`;
            
        const radios = document.querySelectorAll(selector);
        radios.forEach(r => {
            r.checked = true;
            r.dispatchEvent(new Event('change'));
        });
    }

    // Save configuration settings
    const saveBtn = document.getElementById('mas-save-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', () => {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
            
            const formData = new FormData();
            formData.append('action', 'memories_admin_shield_save_settings');
            formData.append('nonce', data.nonce);
            formData.append('settings', JSON.stringify(data.settings));
            
            fetch(data.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(resData => {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
                if (resData.success) {
                    showToast('Settings saved successfully!');
                } else {
                    showToast(resData.data.message || 'Error saving settings.', true);
                }
            })
            .catch(err => {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Changes';
                showToast('Network error while saving.', true);
            });
        });
    }

    // Bypass Toggle button action
    const bypassBtn = document.getElementById('mas-bypass-btn');
    if (bypassBtn) {
        bypassBtn.addEventListener('click', () => {
            bypassBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'memories_admin_shield_toggle_bypass');
            formData.append('nonce', data.nonce);
            
            fetch(data.ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(resData => {
                bypassBtn.disabled = false;
                if (resData.success) {
                    data.bypass = resData.data.bypass;
                    updateBypassUI();
                    showToast(data.bypass ? 'Shield bypassed (Maintenance ON)' : 'Shield active (Filters ON)');
                    
                    // Reload after brief timeout to apply filters instantly
                    setTimeout(() => {
                        window.location.reload();
                    }, 1000);
                } else {
                    showToast(resData.data.message || 'Error toggling bypass', true);
                }
            })
            .catch(err => {
                bypassBtn.disabled = false;
                showToast('Network error.', true);
            });
        });
    }

    function updateBypassUI() {
        const badge = document.getElementById('mas-bypass-badge');
        const btn = document.getElementById('mas-bypass-btn');
        if (!badge || !btn) return;
        
        if (data.bypass) {
            badge.textContent = 'Bypassed (Maintenance ON)';
            badge.className = 'mas-badge mas-badge-active';
            btn.textContent = 'Enable Shield';
            btn.className = 'mas-btn mas-btn-secondary';
        } else {
            badge.textContent = 'Active (Shield ON)';
            badge.className = 'mas-badge mas-badge-inactive';
            btn.textContent = 'Bypass Shield';
            btn.className = 'mas-btn mas-btn-primary';
        }
    }

    function showToast(message, isError = false) {
        const toast = document.getElementById('mas-toast');
        if (!toast) return;
        
        toast.textContent = message;
        if (isError) {
            toast.classList.add('error');
        } else {
            toast.classList.remove('error');
        }
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
});
