(function (wp) {
    const { registerBlockType } = wp.blocks;
    const { TextControl, PanelBody, SelectControl, ToggleControl, RadioControl, Spinner, Button, TabPanel } = wp.components;
    const { InspectorControls } = wp.blockEditor || wp.editor;
    const { Fragment, createElement: el, useState, useEffect } = wp.element;
    const { __ } = wp.i18n;
    const { select } = wp.data;
    const apiFetch = wp.apiFetch;

    registerBlockType('kslc/link-card', {
        title: __('SEOリンクカード', 'kashiwazaki-seo-link-card'),
        icon: 'admin-links',
        category: 'embed',
        keywords: ['link', 'card', 'seo', 'url'],
        attributes: {
            linkType: {
                type: 'string',
                default: 'external'
            },
            url: {
                type: 'string',
                default: ''
            },
            postId: {
                type: 'number',
                default: 0
            },
            title: {
                type: 'string',
                default: ''
            },
            target: {
                type: 'string',
                default: '_self'
            },
            rel: {
                type: 'string',
                default: ''
            },
            useBlank: {
                type: 'boolean',
                default: false
            },
            internalInputType: {
                type: 'string',
                default: 'select' // 'select' or 'url'
            }
        },

        edit: function (props) {
            const { attributes, setAttributes } = props;
            const { linkType, url, postId, title, target, rel, useBlank, internalInputType } = attributes;
            
            const [posts, setPosts] = useState([]);
            const [postTypes, setPostTypes] = useState([]);
            const [selectedPostType, setSelectedPostType] = useState('all');
            const [isLoading, setIsLoading] = useState(false);
            const [searchTerm, setSearchTerm] = useState('');
            const [selectedPost, setSelectedPost] = useState(null);
            // URL検索用の状態
            const [urlSearchTerm, setUrlSearchTerm] = useState('');
            const [urlSearchResults, setUrlSearchResults] = useState([]);
            const [isUrlSearching, setIsUrlSearching] = useState(false);
            // ID入力用の状態
            const [idInput, setIdInput] = useState('');
            const [idSearchResult, setIdSearchResult] = useState(null);
            const [isIdSearching, setIsIdSearching] = useState(false);
            const [idSearchError, setIdSearchError] = useState('');

            // 利用可能な投稿タイプを取得
            // プラグイン自身のエンドポイントを使う（/wp/v2/types は show_in_rest 無効の投稿タイプを返さない）
            const getPostTypes = async () => {
                try {
                    const types = await apiFetch({ path: '/kslc/v1/post-types' });
                    setPostTypes((types || []).map(type => ({
                        value: type.slug,
                        label: type.label,
                        slug: type.slug
                    })));
                } catch (error) {
                    console.error('Error fetching post types:', error);
                }
            };

            // 投稿を検索（カスタムエンドポイントを使用）
            const searchPosts = async (search = '', postType = 'all') => {
                setIsLoading(true);
                try {
                    // カスタムエンドポイントを使用
                    const queryArgs = {
                        search: search || '',
                        post_type: postType,
                        per_page: 100
                    };
                    
                    const allPosts = await apiFetch({
                        path: wp.url.addQueryArgs('/kslc/v1/all-posts', queryArgs)
                    });
                    
                    setPosts(allPosts || []);
                } catch (error) {
                    console.error('Error fetching posts:', error);
                    setPosts([]);
                } finally {
                    setIsLoading(false);
                }
            };

            // URLで投稿を検索
            const searchPostsByUrl = async (urlQuery) => {
                if (!urlQuery || urlQuery.length < 2) {
                    setUrlSearchResults([]);
                    return;
                }
                setIsUrlSearching(true);
                try {
                    const results = await apiFetch({
                        path: wp.url.addQueryArgs('/kslc/v1/search-by-url', {
                            url: urlQuery,
                            per_page: 20
                        })
                    });
                    setUrlSearchResults(results || []);
                } catch (error) {
                    console.error('Error searching by URL:', error);
                    setUrlSearchResults([]);
                } finally {
                    setIsUrlSearching(false);
                }
            };

            // URL検索のデバウンス処理
            useEffect(() => {
                if (urlSearchTerm.length >= 2) {
                    const timer = setTimeout(() => {
                        searchPostsByUrl(urlSearchTerm);
                    }, 300);
                    return () => clearTimeout(timer);
                } else {
                    setUrlSearchResults([]);
                }
            }, [urlSearchTerm]);

            // IDで投稿を検索
            const searchPostById = async (id) => {
                const numId = parseInt(id, 10);
                if (!numId || numId <= 0) {
                    setIdSearchResult(null);
                    setIdSearchError('');
                    return;
                }
                setIsIdSearching(true);
                setIdSearchError('');
                try {
                    const result = await apiFetch({ path: `/kslc/v1/post/${numId}` });
                    setIdSearchResult(result);
                } catch (error) {
                    setIdSearchResult(null);
                    setIdSearchError(__('指定されたIDの記事が見つかりません', 'kashiwazaki-seo-link-card'));
                } finally {
                    setIsIdSearching(false);
                }
            };

            // ID入力のデバウンス処理
            useEffect(() => {
                if (idInput.length > 0) {
                    const timer = setTimeout(() => {
                        searchPostById(idInput);
                    }, 300);
                    return () => clearTimeout(timer);
                } else {
                    setIdSearchResult(null);
                    setIdSearchError('');
                }
            }, [idInput]);

            // 初回読み込み時に投稿タイプと投稿を取得
            useEffect(() => {
                getPostTypes();
                
                if (linkType === 'internal' && internalInputType === 'select') {
                    searchPosts('', selectedPostType);
                    
                    // 既存のpostIdがある場合、その投稿情報を取得
                    if (postId > 0) {
                        apiFetch({ path: `/kslc/v1/post/${postId}` })
                            .then(post => {
                                setSelectedPost({
                                    id: post.id,
                                    title: post.title,
                                    link: post.link
                                });
                            })
                            .catch(error => {
                                console.error('Error fetching post:', error);
                            });
                    }
                }
            }, [linkType, postId, internalInputType]);

            // 投稿タイプが変更されたときに再検索
            useEffect(() => {
                if (linkType === 'internal' && internalInputType === 'select') {
                    searchPosts(searchTerm, selectedPostType);
                }
            }, [selectedPostType]);

            const onChangeLinkType = (newType) => {
                setAttributes({ linkType: newType });
            };

            const onChangeInternalInputType = (newType) => {
                setAttributes({ internalInputType: newType });
            };

            const onClearSettings = () => {
                setAttributes({
                    url: '',
                    postId: 0,
                    title: '',
                    target: '_self',
                    rel: '',
                    useBlank: false
                });
                setSelectedPost(null);
            };

            const onChangeURL = (newURL) => {
                setAttributes({ url: newURL, postId: 0 });
                setSelectedPost(null);
            };

            const onSelectPost = (post) => {
                setSelectedPost(post);
                setAttributes({ 
                    postId: post.id,
                    url: post.link
                });
            };

            const onChangeTitle = (newTitle) => {
                setAttributes({ title: newTitle });
            };

            const onChangeBlank = (newBlank) => {
                setAttributes({ 
                    useBlank: newBlank,
                    target: newBlank ? '_blank' : '_self'
                });
            };

            const onChangeRel = (newRel) => {
                setAttributes({ rel: newRel });
            };

            const onSearchChange = (term) => {
                setSearchTerm(term);
                searchPosts(term, selectedPostType);
            };

            const onUrlSearchChange = (term) => {
                setUrlSearchTerm(term);
            };

            const onSelectUrlResult = (post) => {
                setAttributes({ url: post.link, postId: 0 });
                setSelectedPost({ id: post.id, title: post.title, link: post.link });
                setUrlSearchTerm('');
                setUrlSearchResults([]);
            };

            const onIdInputChange = (value) => {
                setIdInput(value.replace(/[^0-9]/g, ''));
            };

            const onSelectIdResult = (post) => {
                const numId = parseInt(post.id, 10);
                setAttributes({ postId: numId, url: post.link });
                setSelectedPost({ id: numId, title: post.title, link: post.link });
                setIdInput('');
                setIdSearchResult(null);
            };

            const onPostTypeChange = (newType) => {
                setSelectedPostType(newType);
            };

            // ショートコードプレビューの生成
            let shortcodePreview = '';
            if (linkType === 'external' && url) {
                shortcodePreview = `[kashiwazaki_seo_link_card url="${url}"${title ? ` title="${title}"` : ''}${target === '_blank' ? ' target="_blank"' : ''}${rel ? ` rel="${rel}"` : ''}]`;
            } else if (linkType === 'internal') {
                if ((internalInputType === 'select' || internalInputType === 'id') && postId > 0) {
                    shortcodePreview = `[kashiwazaki_seo_link_card post_id="${postId}"${title ? ` title="${title}"` : ''}${target === '_blank' ? ' target="_blank"' : ''}]`;
                } else if (internalInputType === 'url' && url) {
                    shortcodePreview = `[kashiwazaki_seo_link_card url="${url}"${title ? ` title="${title}"` : ''}${target === '_blank' ? ' target="_blank"' : ''}]`;
                }
            } else {
                shortcodePreview = __('リンクを設定してください', 'kashiwazaki-seo-link-card');
            }

            return el(
                Fragment,
                {},
                el(
                    InspectorControls,
                    {},
                    el(
                        PanelBody,
                        { title: __('リンクカード設定', 'kashiwazaki-seo-link-card') },
                        el(RadioControl, {
                            label: __('リンクタイプ', 'kashiwazaki-seo-link-card'),
                            selected: linkType,
                            options: [
                                { label: __('外部リンク', 'kashiwazaki-seo-link-card'), value: 'external' },
                                { label: __('内部リンク', 'kashiwazaki-seo-link-card'), value: 'internal' }
                            ],
                            onChange: onChangeLinkType
                        }),
                        
                        linkType === 'external' ? el(
                            Fragment,
                            {},
                            el(TextControl, {
                                label: __('URL', 'kashiwazaki-seo-link-card'),
                                value: url,
                                onChange: onChangeURL,
                                placeholder: 'https://example.com',
                                help: __('外部サイトのURLを入力してください', 'kashiwazaki-seo-link-card')
                            })
                        ) : el(
                            Fragment,
                            {},
                            el(TabPanel, {
                                className: 'kslc-tab-panel',
                                activeClass: 'is-active',
                                tabs: [
                                    {
                                        name: 'select',
                                        title: __('記事選択', 'kashiwazaki-seo-link-card'),
                                    },
                                    {
                                        name: 'url',
                                        title: __('URL選択', 'kashiwazaki-seo-link-card'),
                                    },
                                    {
                                        name: 'id',
                                        title: __('ID選択', 'kashiwazaki-seo-link-card'),
                                    }
                                ],
                                onSelect: (tabName) => onChangeInternalInputType(tabName)
                            }, (tab) => {
                                if (tab.name === 'id') {
                                    return el('div', { className: 'kslc-id-selector' },
                                        el(TextControl, {
                                            label: __('投稿ID', 'kashiwazaki-seo-link-card'),
                                            value: idInput,
                                            onChange: onIdInputChange,
                                            placeholder: __('投稿IDを入力...', 'kashiwazaki-seo-link-card'),
                                            help: __('投稿のIDを直接入力して検索', 'kashiwazaki-seo-link-card'),
                                            type: 'number'
                                        }),

                                        selectedPost && el('div', {
                                            style: {
                                                padding: '10px',
                                                background: '#e8f4ff',
                                                border: '2px solid #0073aa',
                                                borderRadius: '4px',
                                                marginBottom: '10px'
                                            }
                                        },
                                            el('div', { style: { fontWeight: 'bold', marginBottom: '5px' } },
                                                __('選択中:', 'kashiwazaki-seo-link-card')
                                            ),
                                            el('div', { style: { fontSize: '14px' } }, selectedPost.title),
                                            el('div', { style: { fontSize: '12px', color: '#666', marginTop: '5px' } },
                                                selectedPost.link
                                            )
                                        ),

                                        isIdSearching ? el('div', {
                                            style: { textAlign: 'center', padding: '20px' }
                                        }, el(Spinner)) : (
                                            idSearchResult ? el('div', {
                                                style: {
                                                    border: '1px solid #ddd',
                                                    borderRadius: '4px',
                                                    background: '#fff',
                                                    marginBottom: '10px'
                                                }
                                            },
                                                el('div', {
                                                    onClick: () => onSelectIdResult(idSearchResult),
                                                    style: {
                                                        padding: '12px 15px',
                                                        cursor: 'pointer',
                                                        transition: 'background 0.2s'
                                                    },
                                                    onMouseEnter: (e) => { e.currentTarget.style.background = '#f5f5f5'; },
                                                    onMouseLeave: (e) => { e.currentTarget.style.background = 'white'; }
                                                },
                                                    el('div', {
                                                        style: {
                                                            fontWeight: '500',
                                                            fontSize: '14px',
                                                            color: '#23282d',
                                                            marginBottom: '6px'
                                                        }
                                                    }, idSearchResult.title || '（タイトルなし）'),
                                                    el('div', {
                                                        style: { fontSize: '12px', color: '#0073aa' }
                                                    }, idSearchResult.link),
                                                    el('div', {
                                                        style: { fontSize: '11px', color: '#999', marginTop: '4px' }
                                                    }, `ID: ${idSearchResult.id} / ${idSearchResult.type}`),
                                                    el(Button, {
                                                        isPrimary: true,
                                                        style: { marginTop: '10px' },
                                                        onClick: (e) => { e.stopPropagation(); onSelectIdResult(idSearchResult); }
                                                    }, __('この記事を選択', 'kashiwazaki-seo-link-card'))
                                                )
                                            ) : (
                                                idSearchError && el('div', {
                                                    style: {
                                                        padding: '10px',
                                                        background: '#fef7f1',
                                                        border: '1px solid #f0b849',
                                                        borderRadius: '4px',
                                                        color: '#826200'
                                                    }
                                                }, idSearchError)
                                            )
                                        )
                                    );
                                } else if (tab.name === 'url') {
                                    return el('div', { className: 'kslc-url-selector' },
                                        el(TextControl, {
                                            label: __('URLで検索', 'kashiwazaki-seo-link-card'),
                                            value: urlSearchTerm,
                                            onChange: onUrlSearchChange,
                                            placeholder: __('URLの一部を入力...', 'kashiwazaki-seo-link-card'),
                                            help: __('スラッグやパスの一部で記事を検索できます', 'kashiwazaki-seo-link-card')
                                        }),

                                        selectedPost && el('div', {
                                            style: {
                                                padding: '10px',
                                                background: '#e8f4ff',
                                                border: '2px solid #0073aa',
                                                borderRadius: '4px',
                                                marginBottom: '10px'
                                            }
                                        },
                                            el('div', { style: { fontWeight: 'bold', marginBottom: '5px' } },
                                                __('選択中:', 'kashiwazaki-seo-link-card')
                                            ),
                                            el('div', { style: { fontSize: '14px' } }, selectedPost.title),
                                            el('div', { style: { fontSize: '12px', color: '#666', marginTop: '5px' } },
                                                selectedPost.link
                                            )
                                        ),

                                        isUrlSearching ? el('div', {
                                            style: { textAlign: 'center', padding: '20px' }
                                        }, el(Spinner)) : (
                                            urlSearchResults.length > 0 && el('div', {
                                                style: {
                                                    maxHeight: '300px',
                                                    overflowY: 'auto',
                                                    border: '1px solid #ddd',
                                                    borderRadius: '4px',
                                                    background: '#fff',
                                                    marginBottom: '15px'
                                                }
                                            },
                                                urlSearchResults.map((post, index) =>
                                                    el('div', {
                                                        key: post.id,
                                                        onClick: () => onSelectUrlResult(post),
                                                        style: {
                                                            display: 'block',
                                                            width: '100%',
                                                            padding: '12px 15px',
                                                            borderBottom: index < urlSearchResults.length - 1 ? '1px solid #e0e0e0' : 'none',
                                                            background: 'white',
                                                            cursor: 'pointer',
                                                            transition: 'background 0.2s',
                                                            boxSizing: 'border-box'
                                                        },
                                                        onMouseEnter: (e) => { e.currentTarget.style.background = '#f5f5f5'; },
                                                        onMouseLeave: (e) => { e.currentTarget.style.background = 'white'; }
                                                    },
                                                        el('div', {
                                                            style: {
                                                                fontWeight: '500',
                                                                fontSize: '14px',
                                                                color: '#23282d',
                                                                marginBottom: '6px',
                                                                lineHeight: '1.4',
                                                                wordBreak: 'break-word'
                                                            }
                                                        }, post.title || '（タイトルなし）'),
                                                        el('div', {
                                                            style: {
                                                                fontSize: '12px',
                                                                color: '#0073aa',
                                                                wordBreak: 'break-all'
                                                            }
                                                        }, post.link.replace(/^https?:\/\/[^\/]+/, '')),
                                                        el('div', {
                                                            style: {
                                                                fontSize: '11px',
                                                                color: '#999',
                                                                marginTop: '4px'
                                                            }
                                                        }, post.type)
                                                    )
                                                )
                                            )
                                        ),

                                        el('div', {
                                            style: {
                                                borderTop: '1px solid #ddd',
                                                paddingTop: '15px',
                                                marginTop: '10px'
                                            }
                                        },
                                            el(TextControl, {
                                                label: __('または直接URL入力', 'kashiwazaki-seo-link-card'),
                                                value: url,
                                                onChange: onChangeURL,
                                                placeholder: '/custom-page/',
                                                help: __('WordPressで管理されていない内部ページのURLを入力', 'kashiwazaki-seo-link-card')
                                            })
                                        )
                                    );
                                } else {
                                    return el('div', { className: 'kslc-post-selector' },
                                        el(SelectControl, {
                                            label: __('投稿タイプ', 'kashiwazaki-seo-link-card'),
                                            value: selectedPostType,
                                            options: [
                                                { label: __('すべて', 'kashiwazaki-seo-link-card'), value: 'all' },
                                                ...postTypes.map(type => ({
                                                    label: type.label,
                                                    value: type.slug
                                                }))
                                            ],
                                            onChange: onPostTypeChange
                                        }),
                                        
                                        el(TextControl, {
                                            label: __('記事を検索', 'kashiwazaki-seo-link-card'),
                                            value: searchTerm,
                                            onChange: onSearchChange,
                                            placeholder: __('タイトルで検索...', 'kashiwazaki-seo-link-card')
                                        }),
                                        
                                        selectedPost && el('div', {
                                            style: {
                                                padding: '10px',
                                                background: '#e8f4ff',
                                                border: '2px solid #0073aa',
                                                borderRadius: '4px',
                                                marginBottom: '10px'
                                            }
                                        },
                                            el('div', { style: { fontWeight: 'bold', marginBottom: '5px' } }, 
                                                __('選択中:', 'kashiwazaki-seo-link-card')
                                            ),
                                            el('div', { style: { fontSize: '14px' } }, selectedPost.title),
                                            el('div', { style: { fontSize: '12px', color: '#666', marginTop: '5px' } }, 
                                                selectedPost.link
                                            )
                                        ),
                                        
                                        isLoading ? el('div', { 
                                            style: { 
                                                textAlign: 'center', 
                                                padding: '20px' 
                                            }
                                        }, el(Spinner)) : el('div', {
                                            style: {
                                                maxHeight: '400px',
                                                overflowY: 'auto',
                                                border: '1px solid #ddd',
                                                borderRadius: '4px',
                                                background: '#fff'
                                            }
                                        },
                                            posts.length > 0 ? posts.map((post, index) => 
                                                el('div', {
                                                    key: post.id,
                                                    onClick: () => onSelectPost(post),
                                                    style: {
                                                        display: 'block',
                                                        width: '100%',
                                                        padding: '12px 15px',
                                                        borderBottom: index < posts.length - 1 ? '1px solid #e0e0e0' : 'none',
                                                        background: selectedPost && selectedPost.id === post.id ? '#f0f8ff' : 'white',
                                                        cursor: 'pointer',
                                                        transition: 'background 0.2s',
                                                        boxSizing: 'border-box'
                                                    },
                                                    onMouseEnter: (e) => {
                                                        if (!selectedPost || selectedPost.id !== post.id) {
                                                            e.currentTarget.style.background = '#f5f5f5';
                                                        }
                                                    },
                                                    onMouseLeave: (e) => {
                                                        if (!selectedPost || selectedPost.id !== post.id) {
                                                            e.currentTarget.style.background = 'white';
                                                        } else {
                                                            e.currentTarget.style.background = '#f0f8ff';
                                                        }
                                                    }
                                                },
                                                    el('div', { 
                                                        style: { 
                                                            fontWeight: '500',
                                                            fontSize: '14px',
                                                            color: '#23282d',
                                                            marginBottom: '6px',
                                                            lineHeight: '1.4',
                                                            wordBreak: 'break-word'
                                                        }
                                                    }, post.title || '（タイトルなし）'),
                                                    el('div', { 
                                                        style: { 
                                                            fontSize: '12px',
                                                            color: '#666',
                                                            display: 'flex',
                                                            alignItems: 'center',
                                                            gap: '8px',
                                                            flexWrap: 'wrap'
                                                        }
                                                    }, 
                                                        el('span', {
                                                            style: {
                                                                background: '#e0e0e0',
                                                                padding: '2px 6px',
                                                                borderRadius: '3px',
                                                                fontSize: '11px',
                                                                fontWeight: '500',
                                                                flexShrink: 0
                                                            }
                                                        }, post.type),
                                                        el('span', {
                                                            style: {
                                                                flex: '1 1 auto',
                                                                overflow: 'hidden',
                                                                textOverflow: 'ellipsis',
                                                                whiteSpace: 'nowrap',
                                                                minWidth: 0
                                                            }
                                                        }, post.link.replace(/^https?:\/\/[^\/]+/, ''))
                                                    )
                                                )
                                            ) : el('div', { 
                                                style: { 
                                                    padding: '30px',
                                                    textAlign: 'center',
                                                    color: '#999'
                                                }
                                            }, __('記事が見つかりません', 'kashiwazaki-seo-link-card'))
                                        )
                                    );
                                }
                            })
                        ),
                        
                        el(TextControl, {
                            label: __('カスタムタイトル（オプション）', 'kashiwazaki-seo-link-card'),
                            value: title,
                            onChange: onChangeTitle,
                            help: __('空欄の場合はページのタイトルが自動取得されます', 'kashiwazaki-seo-link-card')
                        }),
                        
                        el(ToggleControl, {
                            label: __('新しいタブで開く', 'kashiwazaki-seo-link-card'),
                            checked: useBlank,
                            onChange: onChangeBlank
                        }),
                        
                        linkType === 'external' && el(SelectControl, {
                            label: __('rel属性', 'kashiwazaki-seo-link-card'),
                            value: rel,
                            options: [
                                { label: __('なし', 'kashiwazaki-seo-link-card'), value: '' },
                                { label: 'nofollow', value: 'nofollow' },
                                { label: 'noopener', value: 'noopener' },
                                { label: 'noreferrer', value: 'noreferrer' },
                                { label: 'nofollow noopener', value: 'nofollow noopener' },
                                { label: 'nofollow noreferrer', value: 'nofollow noreferrer' },
                                { label: 'noopener noreferrer', value: 'noopener noreferrer' },
                                { label: 'nofollow noopener noreferrer', value: 'nofollow noopener noreferrer' }
                            ],
                            onChange: onChangeRel
                        }),

                        el('div', { style: { marginTop: '20px', paddingTop: '15px', borderTop: '1px solid #ddd' } },
                            el(Button, {
                                isSecondary: true,
                                isDestructive: true,
                                onClick: onClearSettings,
                                style: { width: '100%' }
                            }, __('設定をクリア', 'kashiwazaki-seo-link-card'))
                        )
                    )
                ),
                el(
                    'div',
                    { className: 'kslc-block-preview' },
                    el(
                        'div',
                        { 
                            className: 'kslc-block-preview-inner',
                            style: {
                                padding: '15px',
                                border: '2px dashed #ddd',
                                borderRadius: '4px',
                                background: '#f9f9f9',
                                minHeight: '100px',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'center'
                            }
                        },
                        (linkType === 'external' && url) || (linkType === 'internal' && (((internalInputType === 'select' || internalInputType === 'id') && postId > 0) || (internalInputType === 'url' && url))) ? el(
                            'div',
                            { style: { textAlign: 'center', width: '100%' } },
                            el('div', { style: { marginBottom: '10px', fontSize: '14px', color: '#666' } }, 
                                __('リンクカードプレビュー', 'kashiwazaki-seo-link-card')
                            ),
                            el('code', { style: { 
                                display: 'block', 
                                padding: '10px',
                                background: '#fff',
                                border: '1px solid #ddd',
                                borderRadius: '3px',
                                fontSize: '12px',
                                wordBreak: 'break-all'
                            } }, shortcodePreview),
                            el('div', { style: { marginTop: '10px', fontSize: '12px', color: '#999' } },
                                __('保存後、実際のリンクカードが表示されます', 'kashiwazaki-seo-link-card')
                            )
                        ) : el(
                            'div',
                            { style: { color: '#999' } },
                            linkType === 'internal'
                                ? (internalInputType === 'select' || internalInputType === 'id')
                                    ? __('記事を選択してリンクカードを作成', 'kashiwazaki-seo-link-card')
                                    : __('URLを入力してリンクカードを作成', 'kashiwazaki-seo-link-card')
                                : __('URLを入力してリンクカードを作成', 'kashiwazaki-seo-link-card')
                        )
                    )
                )
            );
        },

        save: function (props) {
            const { linkType, url, postId, title, target, rel, internalInputType } = props.attributes;
            
            if (linkType === 'internal') {
                if ((internalInputType === 'select' || internalInputType === 'id') && postId > 0) {
                    let shortcode = `[kashiwazaki_seo_link_card post_id="${postId}"`;
                    if (title) {
                        shortcode += ` title="${title}"`;
                    }
                    if (target === '_blank') {
                        shortcode += ' target="_blank"';
                    }
                    shortcode += ']';
                    return el('div', {}, shortcode);
                } else if (internalInputType === 'url' && url) {
                    let shortcode = `[kashiwazaki_seo_link_card url="${url}"`;
                    if (title) {
                        shortcode += ` title="${title}"`;
                    }
                    if (target === '_blank') {
                        shortcode += ' target="_blank"';
                    }
                    shortcode += ']';
                    return el('div', {}, shortcode);
                }
            } else if (linkType === 'external' && url) {
                let shortcode = `[kashiwazaki_seo_link_card url="${url}"`;
                if (title) {
                    shortcode += ` title="${title}"`;
                }
                if (target === '_blank') {
                    shortcode += ' target="_blank"';
                }
                if (rel) {
                    shortcode += ` rel="${rel}"`;
                }
                shortcode += ']';
                return el('div', {}, shortcode);
            }
            
            return null;
        }
    });
})(window.wp);


/**
 * 文中の文字リンク（書式「SEO文字リンク」）
 * カードのブロック（kslc/link-card）とは別物。選んだ文字を目印付きの普通のリンク <a class="kslc-textlink" href="…"> にする。
 * - エディター上は普通のリンクとして見え、カーソルを置くと編集用の小窓が開く（URL・新しいタブ・リンク解除）
 * - 表示時にサーバー側（kslc_filter_text_link_markers）が転送先への自動追随・rel の自動付与・クリック計測のクラスを足す
 * - プラグインを止めても普通のリンクとして残る
 * 標準のリンク（core/link）は tagName "a"・className null。こちらは className "kslc-textlink" で区別する（rich-text は className ごとに一意）
 */
(function (wp) {
    if (!wp || !wp.richText || !wp.element || !wp.components) {
        return;
    }
    const { registerFormatType, applyFormat, removeFormat, insert, slice, getTextContent, useAnchor } = wp.richText;
    const blockEditor = wp.blockEditor || wp.editor;
    const RichTextToolbarButton = blockEditor && blockEditor.RichTextToolbarButton;
    if (!RichTextToolbarButton || !applyFormat || !useAnchor) {
        return;
    }
    const { Modal, Popover, TextControl, Button, ToggleControl } = wp.components;
    const { Fragment, createElement: el, useState, useEffect } = wp.element;
    const { __ } = wp.i18n;

    const FORMAT_NAME = 'kslc/text-link';

    function isHttpUrl(url) {
        return /^https?:\/\//i.test(String(url).trim()) || /^\/(?!\/)/.test(String(url).trim());
    }

    function makeFormat(url, blank) {
        const attributes = { url: String(url).trim() };
        if (blank) {
            attributes.target = '_blank';
        }
        return { type: FORMAT_NAME, attributes: attributes };
    }

    // カーソル位置を含む目印付きリンクの範囲 [start, end) を返す
    function findFormatBounds(value) {
        const formats = value.formats || [];
        const has = function (i) {
            return !!(formats[i] && formats[i].some(function (f) { return f.type === FORMAT_NAME; }));
        };
        let pos = value.start;
        if (!has(pos) && pos > 0 && has(pos - 1)) {
            pos = pos - 1;
        }
        if (!has(pos)) {
            return null;
        }
        let start = pos;
        let end = pos;
        while (start > 0 && has(start - 1)) {
            start--;
        }
        while (end < formats.length && has(end)) {
            end++;
        }
        return [start, end];
    }

    const settings = {
        title: __('SEO文字リンク', 'kashiwazaki-seo-link-card'),
        tagName: 'a',
        className: 'kslc-textlink',
        attributes: {
            url: 'href',
            target: 'target'
        },
        edit: TextLinkEdit
    };

    function field(child) {
        return el('div', { className: 'kslc-tl-field' }, child);
    }

    function TextLinkEdit(props) {
        const { isActive, activeAttributes, value, onChange, contentRef } = props;
        const [isAdding, setAdding] = useState(false);
        const [isOpen, setOpen] = useState(false);      // リンクの中にカーソルがあるときの小窓
        const [mode, setMode] = useState('view');        // 'view'（URL と操作ボタン）/ 'edit'（入力欄）
        const [url, setUrl] = useState('');
        const [text, setText] = useState('');
        const [blank, setBlank] = useState(false);
        const anchor = useAnchor({ editableContentElement: contentRef && contentRef.current, settings: settings });

        const activeUrl = (activeAttributes && activeAttributes.url) || '';
        const activeBlank = !!(activeAttributes && activeAttributes.target === '_blank');

        // リンクの中にカーソルが入ったら、まず URL と操作ボタンだけの小窓を開く（標準のリンクと同じ流れ）
        useEffect(function () {
            if (isActive) {
                setUrl(activeUrl);
                setBlank(activeBlank);
                setMode('view');
                setOpen(true);
            } else {
                setOpen(false);
            }
        }, [isActive, activeUrl, activeBlank]);

        const openAdd = function () {
            if (isActive) {
                setUrl(activeUrl);
                setBlank(activeBlank);
                setMode('edit');
                setOpen(true);
                return;
            }
            setText(getTextContent(slice(value)));
            setUrl('');
            setBlank(false);
            setAdding(true);
        };

        const add = function () {
            if (!isHttpUrl(url)) {
                return;
            }
            const selected = getTextContent(slice(value));
            const label = text.trim() !== '' ? text.trim() : (selected !== '' ? selected : url.trim());
            let next = value;
            const start = value.start;
            let end = value.end;
            if (label !== selected) {
                // リンク文字を入れ直す（選択が無いときや、文字を書き換えたとき）
                next = insert(value, label);
                end = start + label.length;
            }
            onChange(applyFormat(next, makeFormat(url, blank), start, end));
            setAdding(false);
        };

        const update = function () {
            const bounds = findFormatBounds(value);
            if (!bounds || !isHttpUrl(url)) {
                return;
            }
            let next = removeFormat(value, FORMAT_NAME, bounds[0], bounds[1]);
            next = applyFormat(next, makeFormat(url, blank), bounds[0], bounds[1]);
            onChange(next);
            setMode('view');
        };

        const unlink = function () {
            const bounds = findFormatBounds(value);
            if (!bounds) {
                return;
            }
            onChange(removeFormat(value, FORMAT_NAME, bounds[0], bounds[1]));
            setOpen(false);
        };

        const urlField = el(TextControl, {
            label: __('リンク先 URL', 'kashiwazaki-seo-link-card'),
            value: url,
            type: 'url',
            placeholder: 'https://example.com/',
            onChange: setUrl,
            help: url.trim() !== '' && !isHttpUrl(url) ? __('https:// から始まる URL か、/ から始まるサイト内のパスを入力してください。', 'kashiwazaki-seo-link-card') : undefined,
            __nextHasNoMarginBottom: true,
            __next40pxDefaultSize: true
        });
        const blankField = el(ToggleControl, {
            label: __('新しいタブで開く', 'kashiwazaki-seo-link-card'),
            checked: blank,
            onChange: setBlank,
            __nextHasNoMarginBottom: true
        });

        // 小窓: 表示モード（URL・新しいタブの有無・リンク解除・編集）
        const viewPanel = el('div', { className: 'kslc-tl-panel' },
            el('div', { className: 'kslc-tl-head' },
                el('span', { className: 'kslc-tl-title' }, __('SEO文字リンク', 'kashiwazaki-seo-link-card')),
                el(Button, { icon: 'no-alt', size: 'small', label: __('閉じる', 'kashiwazaki-seo-link-card'), onClick: function () { setOpen(false); } })
            ),
            el('div', { className: 'kslc-tl-url' },
                el('span', { className: 'dashicons dashicons-admin-links', 'aria-hidden': 'true' }),
                el('a', { href: activeUrl, target: '_blank', rel: 'noopener noreferrer', title: activeUrl }, activeUrl.replace(/^https?:\/\//i, ''))
            ),
            el('p', { className: 'kslc-tl-meta' }, activeBlank ? __('新しいタブで開きます', 'kashiwazaki-seo-link-card') : __('同じタブで開きます', 'kashiwazaki-seo-link-card')),
            el('div', { className: 'kslc-tl-foot' },
                el(Button, { variant: 'tertiary', isDestructive: true, onClick: unlink, __next40pxDefaultSize: true }, __('リンク解除', 'kashiwazaki-seo-link-card')),
                el(Button, { variant: 'secondary', onClick: function () { setUrl(activeUrl); setBlank(activeBlank); setMode('edit'); }, __next40pxDefaultSize: true }, __('編集', 'kashiwazaki-seo-link-card'))
            )
        );

        // 小窓: 編集モード（URL・新しいタブ・キャンセル・保存）
        const editPanel = el('div', { className: 'kslc-tl-panel' },
            el('div', { className: 'kslc-tl-head' },
                el('span', { className: 'kslc-tl-title' }, __('SEO文字リンクを編集', 'kashiwazaki-seo-link-card'))
            ),
            field(urlField),
            field(blankField),
            el('div', { className: 'kslc-tl-foot' },
                el(Button, { variant: 'tertiary', onClick: function () { setMode('view'); }, __next40pxDefaultSize: true }, __('キャンセル', 'kashiwazaki-seo-link-card')),
                el(Button, { variant: 'primary', onClick: update, disabled: !isHttpUrl(url), accessibleWhenDisabled: true, __next40pxDefaultSize: true }, __('保存', 'kashiwazaki-seo-link-card'))
            )
        );

        return el(Fragment, {},
            el(RichTextToolbarButton, {
                icon: 'admin-links',
                title: __('SEO文字リンク', 'kashiwazaki-seo-link-card'),
                onClick: openAdd,
                isActive: isActive
            }),
            isAdding && el(Modal, {
                title: __('SEO文字リンクを追加', 'kashiwazaki-seo-link-card'),
                className: 'kslc-tl-modal',
                onRequestClose: function () { setAdding(false); }
            },
                el('p', { className: 'kslc-tl-note' },
                    __('文中の普通のリンクとして表示します（カードにはなりません）。転送先への自動追随・rel の自動付与・リンク切れの検知・クリック計測はカードと同じく効きます。', 'kashiwazaki-seo-link-card')),
                field(urlField),
                field(el(TextControl, {
                    label: __('リンク文字', 'kashiwazaki-seo-link-card'),
                    value: text,
                    onChange: setText,
                    help: __('空のときは選んだ文字、または URL をそのまま使います。', 'kashiwazaki-seo-link-card'),
                    __nextHasNoMarginBottom: true,
                    __next40pxDefaultSize: true
                })),
                field(blankField),
                el('div', { className: 'kslc-tl-foot' },
                    el(Button, { variant: 'tertiary', onClick: function () { setAdding(false); }, __next40pxDefaultSize: true }, __('キャンセル', 'kashiwazaki-seo-link-card')),
                    el(Button, { variant: 'primary', onClick: add, disabled: !isHttpUrl(url), accessibleWhenDisabled: true, __next40pxDefaultSize: true }, __('リンクにする', 'kashiwazaki-seo-link-card'))
                )
            ),
            isActive && isOpen && el(Popover, {
                anchor: anchor,
                placement: 'bottom-start',
                offset: 8,
                focusOnMount: mode === 'edit' ? 'firstElement' : false,
                className: 'kslc-text-link-popover',
                onClose: function () { setOpen(false); }
            }, mode === 'edit' ? editPanel : viewPanel)
        );
    }

    registerFormatType(FORMAT_NAME, settings);
})(window.wp);
