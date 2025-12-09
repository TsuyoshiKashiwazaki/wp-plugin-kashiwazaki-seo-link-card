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

            // 利用可能な投稿タイプを取得
            const getPostTypes = async () => {
                try {
                    const types = await apiFetch({ path: '/wp/v2/types' });
                    const publicTypes = Object.entries(types)
                        .filter(([key, type]) => type.viewable && type.rest_base)
                        .map(([key, type]) => ({
                            value: type.rest_base || key,
                            label: type.name,
                            slug: key
                        }));
                    setPostTypes(publicTypes);
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

            const onPostTypeChange = (newType) => {
                setSelectedPostType(newType);
            };

            // ショートコードプレビューの生成
            let shortcodePreview = '';
            if (linkType === 'external' && url) {
                shortcodePreview = `[kashiwazaki_seo_link_card url="${url}"${title ? ` title="${title}"` : ''}${target === '_blank' ? ' target="_blank"' : ''}${rel ? ` rel="${rel}"` : ''}]`;
            } else if (linkType === 'internal') {
                if (internalInputType === 'select' && postId > 0) {
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
                                        title: __('記事から選択', 'kashiwazaki-seo-link-card'),
                                    },
                                    {
                                        name: 'url',
                                        title: __('URL入力', 'kashiwazaki-seo-link-card'),
                                    }
                                ],
                                onSelect: (tabName) => onChangeInternalInputType(tabName)
                            }, (tab) => {
                                if (tab.name === 'url') {
                                    return el(TextControl, {
                                        label: __('内部URL', 'kashiwazaki-seo-link-card'),
                                        value: url,
                                        onChange: onChangeURL,
                                        placeholder: '/custom-page/',
                                        help: __('WordPressで管理されていない内部ページのURLを入力', 'kashiwazaki-seo-link-card')
                                    });
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
                        (linkType === 'external' && url) || (linkType === 'internal' && ((internalInputType === 'select' && postId > 0) || (internalInputType === 'url' && url))) ? el(
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
                                ? internalInputType === 'select' 
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
                if (internalInputType === 'select' && postId > 0) {
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