jQuery(function($){
  function setColor($control,color){
    $control.find('.pa-color-value').val(color).trigger('change');
    $control.find('.pa-native-color').val(color || '#000000');
    $control.find('.pa-hex-color').val(color);
    $control.find('.pa-color-swatch').removeClass('active').filter(function(){return $(this).data('color') && String($(this).data('color')).toLowerCase()===String(color).toLowerCase();}).addClass('active');
    updatePreview(); updateAgendaPreview(); updateSpeakerCardPreview(); updateProgramPagePreviews();
    var $categoryRow=$control.closest('.pa-category-row');
    if($categoryRow.length){ refreshCategoryIcon($categoryRow); }
  }
  $(document).on('click','.pa-color-swatch',function(){setColor($(this).closest('.pa-color-control'),$(this).data('color'));});
  $(document).on('click','.pa-more-colors',function(){ $(this).closest('.pa-color-control').find('.pa-color-popover').prop('hidden',false); });
  $(document).on('click','.pa-close-color',function(){ $(this).closest('.pa-color-popover').prop('hidden',true); });
  $(document).on('click','.pa-clear-color',function(){setColor($(this).closest('.pa-color-control'),'');});
  $(document).on('input change','.pa-native-color,.pa-hex-color',function(){setColor($(this).closest('.pa-color-control'),$(this).val());});

  $(document).on('click','.pa-upload-image',function(e){
    e.preventDefault();
    var $field=$(this).closest('.pa-image-field');
    var frame=wp.media({title:'Choose image',button:{text:'Use this image'},multiple:false});
    frame.on('select',function(){
      var att=frame.state().get('selection').first().toJSON();
      var url=att.sizes&&att.sizes.thumbnail?att.sizes.thumbnail.url:att.url;
      $field.find('input[type=hidden]').val(att.id);
      $field.find('.pa-image-preview').empty().append($('<img>').attr({src:url,alt:''}));
    });
    frame.open();
  });
  $(document).on('click','.pa-upload-multiple-images',function(e){
    e.preventDefault();
    var $field=$(this).closest('.pa-multi-image-field');
    var frame=wp.media({title:'Choose sponsor logos',button:{text:'Use these logos'},multiple:true});
    frame.on('select',function(){
      var ids=[], $preview=$field.find('.pa-image-preview').empty();
      frame.state().get('selection').each(function(model){
        var att=model.toJSON();
        var url=att.sizes&&att.sizes.thumbnail?att.sizes.thumbnail.url:att.url;
        ids.push(att.id);
        $('<span>').addClass('pa-multi-image-thumb').attr('data-id',att.id).append($('<img>').attr({src:url,alt:''})).appendTo($preview);
      });
      $field.find('input[type=hidden]').val(ids.join(','));
    });
    frame.open();
  });
  $(document).on('click','.pa-remove-image',function(e){e.preventDefault();var $f=$(this).closest('.pa-image-field');$f.find('input[type=hidden]').val('');$f.find('.pa-image-preview').empty();});

  function updateSpeakerOrder(){var ids=[];$('.pa-selected-speakers li').each(function(){ids.push($(this).data('id'));});$('.pa-speaker-order').val(ids.join(','));}
  function speakerCategorySelectHtml(id){var html=$('#pa-speaker-category-select-template').html()||''; return html.replace(/__SPEAKER_ID__/g,id);}
  $('.pa-selected-speakers').sortable({update:updateSpeakerOrder});
  $(document).on('input','.pa-speaker-search',function(){var q=String($(this).val()||'').toLowerCase();$('.pa-speaker-picker label').each(function(){var terms=String($(this).data('name')||'').toLowerCase();$(this).toggle(terms.indexOf(q)!==-1);});});

  $(document).on('input','.pa-admin-list-search',function(){
    var q=String($(this).val()||'').toLowerCase().trim();
    var $wrap=$(this).closest('.pa-wrap');
    var visible=0;
    $wrap.find('.pa-searchable-row').each(function(){
      var $row=$(this);
      var terms=String($row.attr('data-pa-search')||'').toLowerCase();
      var show=!q || terms.indexOf(q)!==-1;
      $row.toggle(show);
      if(show){ visible++; }
      var key=$row.attr('data-pa-row-key');
      if(key && !show){
        $wrap.find('[data-pa-detail-for="'+key+'"]').addClass('pa-is-hidden').attr('aria-hidden','true').css('display','none');
        $row.find('.pa-program-events-toggle').attr('aria-expanded','false').removeClass('is-open');
      }
    });
    $wrap.find('.pa-list-search-empty').prop('hidden', visible!==0 || !q);
  });
  $(document).on('change','.pa-speaker-check',function(){
    var id=$(this).val(), name=$(this).closest('label').text().trim(), $list=$('.pa-selected-speakers');
    if(this.checked){
      if(!$list.find('li[data-id="'+id+'"]').length){
        $list.append('<li data-id="'+id+'"><span class="pa-selected-speaker-name">'+name+'</span>'+speakerCategorySelectHtml(id)+'<span class="pa-selected-speaker-actions"><button type="button" class="button-link pa-move-speaker-up" aria-label="Move up" title="Move up"><span aria-hidden="true">▲</span><span class="screen-reader-text">Move up</span></button> <button type="button" class="button-link pa-move-speaker-down" aria-label="Move down" title="Move down"><span aria-hidden="true">▼</span><span class="screen-reader-text">Move down</span></button> <button type="button" class="button-link pa-remove-speaker">Remove</button></span></li>');
      }
    }
    else {$list.find('li[data-id="'+id+'"]').remove();}
    updateSpeakerOrder();
  });
  $(document).on('click','.pa-remove-speaker',function(){var id=$(this).closest('li').data('id');$('.pa-speaker-check[value="'+id+'"]').prop('checked',false);$(this).closest('li').remove();updateSpeakerOrder();});
  $(document).on('click','.pa-select-all-speakers',function(e){e.preventDefault();$('.pa-speaker-picker label:visible .pa-speaker-check').each(function(){if(!this.checked){$(this).prop('checked',true).trigger('change');}});});
  $(document).on('click','.pa-move-speaker-up',function(e){e.preventDefault();var $li=$(this).closest('li'),$prev=$li.prev('li'); if($prev.length){$li.insertBefore($prev); updateSpeakerOrder();}});
  $(document).on('click','.pa-move-speaker-down',function(e){e.preventDefault();var $li=$(this).closest('li'),$next=$li.next('li'); if($next.length){$li.insertAfter($next); updateSpeakerOrder();}});


  $(document).on('input','.pa-speaker-event-search',function(){
    var q=String($(this).val()||'').toLowerCase();
    $('.pa-speaker-event-picker label').each(function(){
      var terms=String($(this).data('name')||'').toLowerCase();
      $(this).toggle(terms.indexOf(q)!==-1);
    });
  });
  $(document).on('change','.pa-speaker-event-check',function(){
    var id=$(this).val(), name=$(this).closest('label').text().trim(), $list=$('.pa-selected-speaker-events');
    if(this.checked){
      if(!$list.find('li[data-id="'+id+'"]').length){
        $list.append('<li data-id="'+id+'"><span class="pa-selected-speaker-event-name">'+name+'</span><button type="button" class="button-link pa-remove-speaker-event">Remove</button></li>');
      }
    } else {
      $list.find('li[data-id="'+id+'"]').remove();
    }
  });
  $(document).on('click','.pa-remove-speaker-event',function(e){
    e.preventDefault();
    var id=$(this).closest('li').data('id');
    $('.pa-speaker-event-check[value="'+id+'"]').prop('checked',false);
    $(this).closest('li').remove();
  });
  $(document).on('click','.pa-select-all-speaker-events',function(e){
    e.preventDefault();
    $('.pa-speaker-event-picker label:visible .pa-speaker-event-check').each(function(){
      if(!this.checked){ $(this).prop('checked',true).trigger('change'); }
    });
  });

  function updateSponsorOrder(){var ids=[];$('.pa-selected-sponsors li').each(function(){ids.push($(this).data('id'));});$('.pa-sponsor-order').val(ids.join(','));}
  $('.pa-selected-sponsors').sortable({update:updateSponsorOrder});
  $(document).on('input','.pa-sponsor-search',function(){var q=String($(this).val()||'').toLowerCase();$('.pa-sponsor-picker label').each(function(){var terms=String($(this).data('name')||'').toLowerCase();$(this).toggle(terms.indexOf(q)!==-1);});});
  $(document).on('change','.pa-sponsor-check',function(){
    var id=$(this).val(), name=$(this).closest('label').text().trim(), $list=$('.pa-selected-sponsors');
    if(this.checked){ if(!$list.find('li[data-id="'+id+'"]').length){$list.append('<li data-id="'+id+'"><span class="pa-selected-sponsor-name">'+name+'</span><span class="pa-selected-sponsor-actions"><button type="button" class="button-link pa-move-sponsor-up" aria-label="Move up" title="Move up"><span aria-hidden="true">▲</span><span class="screen-reader-text">Move up</span></button> <button type="button" class="button-link pa-move-sponsor-down" aria-label="Move down" title="Move down"><span aria-hidden="true">▼</span><span class="screen-reader-text">Move down</span></button> <button type="button" class="button-link pa-remove-sponsor">Remove</button></span></li>');} }
    else {$list.find('li[data-id="'+id+'"]').remove();}
    updateSponsorOrder();
  });
  $(document).on('click','.pa-remove-sponsor',function(){var id=$(this).closest('li').data('id');$('.pa-sponsor-check[value="'+id+'"]').prop('checked',false);$(this).closest('li').remove();updateSponsorOrder();});
  $(document).on('click','.pa-select-all-sponsors',function(e){e.preventDefault();$('.pa-sponsor-picker label:visible .pa-sponsor-check').each(function(){if(!this.checked){$(this).prop('checked',true).trigger('change');}});});
  $(document).on('click','.pa-move-sponsor-up',function(e){e.preventDefault();var $li=$(this).closest('li'),$prev=$li.prev('li'); if($prev.length){$li.insertBefore($prev); updateSponsorOrder();}});
  $(document).on('click','.pa-move-sponsor-down',function(e){e.preventDefault();var $li=$(this).closest('li'),$next=$li.next('li'); if($next.length){$li.insertAfter($next); updateSponsorOrder();}});

  function currentCategoryList(){
    var pid=$('.pa-program-category-source').val(), all=(window.paProgramAgenda&&paProgramAgenda.programCategories)||{}, cats=[];
    if(pid && all[pid]){ cats=all[pid]||[]; }
    else { Object.keys(all).forEach(function(k){ (all[k]||[]).forEach(function(c){ if(cats.indexOf(c)===-1) cats.push(c); }); }); }
    return cats;
  }
  function updateCategoryOptions(){
    var cats=currentCategoryList();
    var $picker=$('[data-pa-category-pill-picker]');
    if($picker.length){
      var customCats=$picker.data('customCategories') || [];
      customCats.forEach(function(c){ if(cats.indexOf(c)===-1){ cats.push(c); } });
      var $pills=$picker.find('.pa-category-pills').empty();
      if(!cats.length){ $pills.append($('<span>').addClass('pa-category-pill-empty').text('No categories yet. Type a new one, then press Enter to add it.')); }
      cats.forEach(function(c){
        $('<button type="button">').addClass('pa-category-pill').attr('data-category',c).text(c).toggleClass('active', String($('.pa-event-category-input').val()||'').toLowerCase()===String(c).toLowerCase()).appendTo($pills);
      });
    }
  }
  function updateProgramDateOptions(){
    var pid=$('.pa-program-date-source').val(), all=(window.paProgramAgenda&&paProgramAgenda.programDates)||{}, dates=pid && all[pid] ? all[pid] : {};
    var current=$('[name="event_date"]').val();
    var $sel=$('.pa-event-program-date-select'); if(!$sel.length) return;
    $sel.empty().append($('<option>').val('').text('Select a date'));
    Object.keys(dates).forEach(function(value){ $sel.append($('<option>').val(value).text(dates[value])); });
    $sel.append($('<option>').val('__custom__').text('Add another date'));
    if(current && dates[current]){ $sel.val(current); } else if(current){ $sel.val('__custom__'); }
  }
  $(document).on('click','.pa-category-pill',function(e){
    e.preventDefault();
    $('.pa-event-category-input').val($(this).data('category')).trigger('input');
    updateCategoryOptions();
  });
  var paCategoryEnterHandledAt = 0;
  function addCategoryPillFromInput(input){
    var $input=$(input);
    var val=String($input.val()||'').trim();
    if(!val){ return; }
    var $picker=$('[data-pa-category-pill-picker]');
    var customCats=$picker.data('customCategories') || [];
    var exists=currentCategoryList().concat(customCats).some(function(c){ return String(c).toLowerCase()===val.toLowerCase(); });
    if(!exists){ customCats.push(val); $picker.data('customCategories',customCats); }
    $input.val(val);
    updateCategoryOptions();
  }
  $(document).on('keydown keypress keyup','.pa-event-category-input',function(e){
    if(e.key === 'Enter' || e.which === 13){
      e.preventDefault();
      e.stopPropagation();
      if(e.stopImmediatePropagation){ e.stopImmediatePropagation(); }
      paCategoryEnterHandledAt = Date.now();
      if(e.type === 'keydown' || e.type === 'keypress'){ addCategoryPillFromInput(this); }
      return false;
    }
  });
  $(document).on('submit','.pa-event-form',function(e){
    var active=document.activeElement;
    if($(active).is('.pa-event-category-input') && Date.now() - paCategoryEnterHandledAt < 1000){
      e.preventDefault();
      e.stopPropagation();
      if(e.stopImmediatePropagation){ e.stopImmediatePropagation(); }
      return false;
    }
  });
  $(document).on('input','.pa-event-category-input',updateCategoryOptions);
  $(document).on('change','.pa-program-category-source',function(){updateCategoryOptions(); updateProgramDateOptions();}); updateCategoryOptions(); updateProgramDateOptions();
  $(document).on('change','.pa-event-program-date-select',function(){var v=$(this).val(); if(v && v !== '__custom__'){$('[name="event_date"]').val(v).trigger('change');} if(v==='__custom__'){$('[name="event_date"]').focus();}});
  $(document).on('change input','[name="event_date"]',updateProgramDateOptions);

  var iconMap={heart:'♥',circle:'●',triangle:'▲',square:'■',star:'★',none:''};
  function refreshCategoryIcon($row){
    var icon=$row.find('.pa-category-icon-select').val() || 'none';
    var color=$row.find('.pa-color-value').val() || '';
    $row.find('.pa-category-icon-preview').text(iconMap[icon] || '').css({color:color || '', background:'transparent'});
  }
  $(document).on('click','.pa-add-category',function(){
    var $wrap=$('#pa-categories'), tpl=$('#pa-category-template').html(), i=$wrap.children('.pa-category-row').length;
    var $row=$(tpl.replaceAll('__INDEX__',i));
    $wrap.append($row);
    refreshCategoryIcon($row);
  });
  $(document).on('click','.pa-add-speaker-category',function(e){e.preventDefault();var $wrap=$('#pa-speaker-categories'), tpl=$('#pa-speaker-category-template').html(), i=$wrap.children('.pa-speaker-category-row').length;$wrap.append($(tpl.replaceAll('__INDEX__',i)));});
  $(document).on('click','.pa-remove-speaker-category-link',function(e){e.preventDefault();$(this).closest('.pa-speaker-category-row').remove();});
  $(document).on('change','.pa-category-icon-select',function(){refreshCategoryIcon($(this).closest('.pa-category-row'));});
  $('.pa-category-row').each(function(){refreshCategoryIcon($(this));});
  function syncAllCategoriesSame(){
    var $toggle=$('.pa-all-categories-same'); if(!$toggle.length || !$toggle.is(':checked')) return;
    var $first=$('#pa-categories .pa-category-row:first'); if(!$first.length) return;
    var color=$first.find('.pa-color-value').val() || '#000000';
    var icon=$first.find('.pa-category-icon-select').val() || 'none';
    $('#pa-categories .pa-category-row').not($first).each(function(){
      setColor($(this).find('.pa-color-control'), color);
      $(this).find('.pa-category-icon-select').val(icon);
      refreshCategoryIcon($(this));
    });
  }
  $(document).on('change','.pa-all-categories-same,.pa-category-icon-select',syncAllCategoriesSame);
  $(document).on('change','.pa-category-row .pa-color-value',syncAllCategoriesSame);

  $(document).on('click','.pa-remove-row',function(e){
    e.preventDefault();
    var $row=$(this).closest('.pa-category-row');
    if(window.localStorage && localStorage.getItem('paHideCategoryRemoveWarning')==='1'){$row.remove(); return;}
    $row.find('.pa-category-remove-warning').prop('hidden',false);
  });
  $(document).on('click','.pa-confirm-remove-category',function(e){
    e.preventDefault();
    var $row=$(this).closest('.pa-category-row');
    if($row.find('.pa-hide-category-warning').is(':checked') && window.localStorage){localStorage.setItem('paHideCategoryRemoveWarning','1');}
    $row.remove();
  });


  $(document).on('click','.pa-program-advanced-tabs button',function(){
    var tab=$(this).data('pa-program-tab');
    $(this).addClass('active').siblings().removeClass('active');
    $('.pa-advanced-tab-panel').removeClass('active').filter('[data-pa-program-panel="'+tab+'"]').addClass('active');
  });

  function paSetProgramPreview(target){
    target = target || '';
    $('.pa-program-preview-stage').attr('data-pa-active-preview', target);
    $('.pa-program-preview-panel').each(function(){
      var isActive = !!target && $(this).data('pa-preview-panel') === target;
      $(this).toggleClass('is-active', isActive).prop('hidden', !isActive).attr('aria-hidden', isActive ? 'false' : 'true');
    });
  }
  function paOpenProgramAdvancedSection(target){
    target = target || 'agenda';
    var $accordion = $('.pa-program-advanced-accordion');
    $accordion.attr('data-pa-active-section', target);
    $accordion.find('.pa-program-advanced-tab-button').each(function(){
      var isActive = $(this).data('pa-preview-target') === target;
      $(this).toggleClass('is-active', isActive).attr('aria-selected', isActive ? 'true' : 'false');
    });
    $accordion.find('.pa-program-advanced-accordion-item').each(function(){
      var $item = $(this);
      var isActive = $item.data('pa-preview-target') === target;
      $item.toggleClass('is-active', isActive);
      $item.find('> .pa-program-advanced-accordion-trigger').attr('aria-expanded', isActive ? 'true' : 'false');
      $item.find('> .pa-program-advanced-subsection-inner, > .pa-program-advanced-subsection-inner').prop('hidden', !isActive);
      $item.find('.pa-program-advanced-subsection-inner').first().prop('hidden', !isActive);
    });
    paSetProgramPreview(target);
  }
  $(document).on('click','.pa-program-advanced-tab-button,.pa-program-advanced-accordion-trigger',function(e){
    e.preventDefault();
    var target = $(this).data('pa-preview-target') || $(this).closest('.pa-program-advanced-accordion-item').data('pa-preview-target') || 'agenda';
    paOpenProgramAdvancedSection(target);
  });
  $(function(){
    if($('.pa-program-advanced-accordion-item').length){
      paOpenProgramAdvancedSection('agenda');
    }
  });

  $(document).on('click','.pa-additional-dates-toggle',function(e){
    e.preventDefault();
    var $wrap=$('#pa-additional-dates'), tpl=$('#pa-additional-date-template').html();
    if(!$wrap.length || !tpl) return;
    var i=$wrap.children('.pa-additional-date-row').length;
    $wrap.append($(tpl.replaceAll('__INDEX__',i)));
  });
  $(document).on('click','.pa-remove-additional-date',function(e){
    e.preventDefault();
    $(this).closest('.pa-additional-date-row').remove();
  });
  function reindexSponsorLevels(){
    $('#pa-sponsor-levels .pa-sponsor-level-row').each(function(i){
      $(this).find('input[type="text"]').attr('name','sponsor_levels['+i+']');
    });
  }
  $('#pa-sponsor-levels').sortable({handle:'.pa-sponsor-level-handle', update:reindexSponsorLevels});
  $(document).on('click','.pa-add-sponsor-level',function(e){
    e.preventDefault();
    var $wrap=$('#pa-sponsor-levels'), tpl=$('#pa-sponsor-level-template').html();
    if(!$wrap.length || !tpl) return;
    var i=$wrap.children('.pa-sponsor-level-row').length;
    $wrap.append($(tpl.replaceAll('__INDEX__',i)));
    reindexSponsorLevels();
  });
  $(document).on('click','.pa-remove-sponsor-level',function(e){
    e.preventDefault();
    $(this).closest('.pa-sponsor-level-row').remove();
    reindexSponsorLevels();
  });
  $(document).on('click','.pa-move-sponsor-level-up',function(e){
    e.preventDefault();
    var $row=$(this).closest('.pa-sponsor-level-row'), $prev=$row.prev('.pa-sponsor-level-row');
    if($prev.length){ $row.insertBefore($prev); reindexSponsorLevels(); }
  });
  $(document).on('click','.pa-move-sponsor-level-down',function(e){
    e.preventDefault();
    var $row=$(this).closest('.pa-sponsor-level-row'), $next=$row.next('.pa-sponsor-level-row');
    if($next.length){ $row.insertAfter($next); reindexSponsorLevels(); }
  });
  reindexSponsorLevels();
  function updateSponsorLevelCheckboxes(){
    var $source=$('.pa-sponsor-program-source'), $box=$('.pa-sponsor-level-options');
    if(!$source.length || !$box.length) return;
    var pid=$source.val(), all=(window.paProgramAgenda&&paProgramAgenda.programSponsorLevels)||{}, levels=pid&&all[pid]?all[pid]:[];
    var selected=[]; try{ selected=JSON.parse($box.attr('data-selected')||'[]')||[]; }catch(e){ selected=[]; }
    $box.empty();
    levels.forEach(function(level){
      var id='pa-sponsor-level-'+String(level).toLowerCase().replace(/[^a-z0-9]+/g,'-');
      var $label=$('<label>').addClass('pa-sponsor-level-choice').attr('for',id);
      $('<input type="checkbox">').attr({id:id,name:'sponsor_levels[]',value:level}).prop('checked',selected.indexOf(level)!==-1).appendTo($label);
      $label.append(' '+level).appendTo($box);
    });
    $('.pa-sponsor-level-empty').prop('hidden', levels.length!==0);
  }
  $(document).on('change','.pa-sponsor-program-source',function(){
    $('.pa-sponsor-level-options').attr('data-selected','[]');
    updateSponsorLevelCheckboxes();
  });
  updateSponsorLevelCheckboxes();

  function firstNonBlank($fields){
    var value=$fields.first().val();
    $fields.each(function(){
      var current=$(this).val();
      if(current !== ''){ value=current; return false; }
    });
    return value;
  }
  function syncLockedBorderFields($group,type,$source){
    var isRadius=type==='radius';
    var $lock=$group.find(isRadius ? '.pa-lock-radius' : '.pa-lock-width');
    if(!$lock.is(':checked')) return;
    var $fields=$group.find(isRadius ? '.pa-radius-input' : '.pa-width-input');
    var value=$source && $source.is('input[type=number]') ? $source.val() : firstNonBlank($fields);
    $fields.val(value);
    updatePreview(); updateProgramPagePreviews(); updateAgendaPreview(); updateSpeakerCardPreview();
  }
  $(document).on('change','.pa-lock-radius',function(){syncLockedBorderFields($(this).closest('.pa-border-control'),'radius',$(this));});
  $(document).on('change','.pa-lock-width',function(){syncLockedBorderFields($(this).closest('.pa-border-control'),'width',$(this));});
  $(document).on('input change','.pa-radius-input',function(){syncLockedBorderFields($(this).closest('.pa-border-control'),'radius',$(this));});
  $(document).on('input change','.pa-width-input',function(){syncLockedBorderFields($(this).closest('.pa-border-control'),'width',$(this));});

  function borderCss(prefix){var css={}, map={tl:'TopLeft',tr:'TopRight',br:'BottomRight',bl:'BottomLeft'}; ['tl','tr','br','bl'].forEach(function(k){var v=$('[data-preview="'+prefix+'_radius_'+k+'"]').val(); if(v!=='') css['border'+map[k]+'Radius']=v+'px';}); ['top','right','bottom','left'].forEach(function(k){var v=$('[data-preview="'+prefix+'_width_'+k+'"]').val(); if(v!==''){css['border-'+k+'-width']=v+'px';css.borderStyle='solid';}}); var c=$('[data-preview="'+prefix+'_color"]').val(); if(c){css.borderColor=c;css.borderStyle='solid';} return css;}
  function forcePreviewColor($els, color){
    $els.each(function(){
      if(color){ this.style.setProperty('color', color, 'important'); }
      else { this.style.removeProperty('color'); }
    });
  }
  function updatePreview(){
    var $h=$('.pa-preview-header'), $c=$('.pa-preview-content'), $img=$('.pa-preview-image');
    if(!$h.length) return;
    $h.attr('style',''); $c.attr('style',''); $img.attr('style','');
    var hb=$('[data-preview="header_bg"]').val(), hc=$('[data-preview="header_color"]').val(), cb=$('[data-preview="content_bg"]').val(), cc=$('[data-preview="content_color"]').val();
    if(hb) $h.css('background-color',hb); if(cb) $c.css('background-color',cb);
    if(hc){ forcePreviewColor($h.add($h.find('*')), hc); }
    else { forcePreviewColor($h.add($h.find('*')), ''); }
    if(cc){ forcePreviewColor($c.add($c.find('*')), cc); }
    else { forcePreviewColor($c.add($c.find('*')), ''); }
    $h.css(borderCss('header_border')); $c.css(borderCss('content_border'));
    var shape=$('[data-preview="image_shape"]').val(); if(shape==='circle') $img.css('border-radius','50%'); if(shape==='square') $img.css('border-radius','0');
    var bw=$('[data-preview="image_border_width"]').val(), bc=$('[data-preview="image_border_color"]').val(); if(bw!=='') $img.css({borderStyle:'solid',borderWidth:bw+'px'}); if(bc) $img.css('borderColor',bc); if(bc){ $img.css('background-color',bc); }
  }
  $(document).on('change input','.pa-preview-input',updatePreview); updatePreview();

  function fieldByName(name){
    return $('input,select,textarea').filter(function(){ return this.name === name; });
  }
  function pageValue(root, key){
    var $field = fieldByName(root+'['+key+']').first();
    return $field.length ? ($field.val() || '') : '';
  }
  function pageNestedValue(root, group, key){
    var $field = fieldByName(root+'['+group+']['+key+']').first();
    return $field.length ? ($field.val() || '') : '';
  }
  function setImportant($els, prop, value){
    $els.each(function(){
      if(value !== undefined && value !== null && value !== ''){ this.style.setProperty(prop, value, 'important'); }
      else { this.style.removeProperty(prop); }
    });
  }
  function pageBorderCss(root, group){
    var css={}, map={tl:'TopLeft',tr:'TopRight',br:'BottomRight',bl:'BottomLeft'};
    ['tl','tr','br','bl'].forEach(function(k){
      var v=pageNestedValue(root,group,'radius_'+k);
      if(v !== ''){ css['border'+map[k]+'Radius']=v+'px'; }
    });
    ['top','right','bottom','left'].forEach(function(k){
      var v=pageNestedValue(root,group,'width_'+k);
      if(v !== ''){ css['border-'+k+'-width']=v+'px'; css.borderStyle='solid'; }
    });
    var c=pageNestedValue(root,group,'color');
    if(c){ css.borderColor=c; css.borderStyle='solid'; }
    return css;
  }
  function applyImportantCss($el, css){
    Object.keys(css || {}).forEach(function(prop){
      var cssProp = prop.replace(/[A-Z]/g,function(m){return '-'+m.toLowerCase();});
      setImportant($el, cssProp, css[prop]);
    });
  }
  function forcePreviewColor($els, color){
    setImportant($els, 'color', color || '');
  }
  function applyProgramPagePreview(root, type){
    var $preview=$('.pa-live-preview[data-program-page-preview="'+type+'"]');
    if(!$preview.length) return;
    var $header=$preview.find('> .pa-preview-header');
    var $content=$preview.find('> .pa-preview-content');
    var $image=$preview.find('.pa-preview-image');

    ['background-color','border-color','border-style','border-width','border-top-width','border-right-width','border-bottom-width','border-left-width','border-top-left-radius','border-top-right-radius','border-bottom-right-radius','border-bottom-left-radius'].forEach(function(prop){
      $header.add($content).each(function(){ this.style.removeProperty(prop); });
    });
    forcePreviewColor($header.add($header.find('*')), '');
    forcePreviewColor($content.add($content.find('*')), '');
    $image.each(function(){
      ['border-radius','border-style','border-width','border-color','background-color'].forEach(function(prop){ this.style.removeProperty(prop); }, this);
    });

    var headerBg=pageValue(root,'header_bg');
    var headerColor=pageValue(root,'header_color');
    var contentBg=pageValue(root,'content_bg');
    var contentColor=pageValue(root,'content_color');

    setImportant($header, 'background-color', headerBg);
    setImportant($content, 'background-color', contentBg);
    forcePreviewColor($header.add($header.find('*')), headerColor);
    forcePreviewColor($content.add($content.find('*')), contentColor);
    applyImportantCss($header, pageBorderCss(root,'header_border'));
    applyImportantCss($content, pageBorderCss(root,'content_border'));

    if(type === 'speaker' && $image.length){
      var shape=pageValue(root,'image_shape');
      if(shape === 'circle'){ setImportant($image, 'border-radius', '50%'); }
      else if(shape === 'square'){ setImportant($image, 'border-radius', '0'); }
      var bw=pageValue(root,'image_border_width');
      var bc=pageValue(root,'image_border_color');
      if(bw !== ''){ setImportant($image, 'border-style', 'solid'); setImportant($image, 'border-width', bw+'px'); }
      if(bc){ setImportant($image, 'border-color', bc); }
    }
  }
  function updateProgramPagePreviews(){
    applyProgramPagePreview('event_page_settings','event');
    applyProgramPagePreview('speaker_page_settings','speaker');
  }
  $(document).on('change input keyup','[name^="event_page_settings"],[name^="speaker_page_settings"],.pa-program-page-settings-panel .pa-color-value',updateProgramPagePreviews);
  updateProgramPagePreviews();


  function applyProgramBorder($el, group){
    var map={tl:'TopLeft',tr:'TopRight',br:'BottomRight',bl:'BottomLeft'};
    ['tl','tr','br','bl'].forEach(function(k){
      var v=$('[name="'+group+'[radius_'+k+']"]').val();
      if(v !== undefined && v !== ''){ $el.css('border'+map[k]+'Radius', v+'px'); }
    });
    ['top','right','bottom','left'].forEach(function(k){
      var v=$('[name="'+group+'[width_'+k+']"]').val();
      if(v !== undefined && v !== ''){ $el.css('border-'+k+'-width', v+'px'); $el.css('border-style','solid'); }
    });
  }
  function setProgramBorder(group, data){
    data=data || {};
    $('[name="'+group+'[lock_radius]"]').prop('checked', !!data.lock_radius);
    $('[name="'+group+'[lock_width]"]').prop('checked', !!data.lock_width);
    ['tl','tr','br','bl'].forEach(function(k){
      var v=(data['radius_'+k] !== undefined && data['radius_'+k] !== '') ? data['radius_'+k] : ((data.border_radius !== undefined && data.border_radius !== '') ? data.border_radius : '0');
      $('[name="'+group+'[radius_'+k+']"]').val(v);
    });
    ['top','right','bottom','left'].forEach(function(k){
      var v=(data['width_'+k] !== undefined && data['width_'+k] !== '') ? data['width_'+k] : ((data.border_width !== undefined && data.border_width !== '') ? data.border_width : '0');
      $('[name="'+group+'[width_'+k+']"]').val(v);
    });
  }

  function updateSpeakerCardPreview(){
    var $p=$('.pa-speaker-card-preview'); if(!$p.length) return; $p.attr('style','');
    var $text=$p.find('h3 a,.pa-speaker-card-role,.pa-speaker-card-company').attr('style','');
    var bg=$('[name="speaker_card[background]"]').val(), col=$('[name="speaker_card[color]"]').val(), bc=$('[name="speaker_card[border_color]"]').val();
    if(bg) $p.css('background-color',bg);
    if(col){ $p.css('color',col); $text.css('color',col); }
    applyProgramBorder($p,'speaker_card'); if(bc) $p.css('borderColor',bc);
    $('.pa-speaker-card-preview-thumb').toggle($('[name="speaker_card[show_thumbnail]"]').is(':checked'));
    var shape=$('[name="speaker_card[thumbnail_shape]"]').val(); $('.pa-speaker-card-preview-thumb').css('border-radius', shape==='circle'?'50%':(shape==='square'?'0':'8px'));
  }
  $(document).on('change input','.pa-speaker-card-live-field,[name^="speaker_card"]',updateSpeakerCardPreview); updateSpeakerCardPreview();

  function updateAgendaPreview(){
    var $p=$('.pa-event-card-preview'); if(!$p.length) return;
    var $tabs=$('.pa-agenda-tabs-preview');
    $p.attr('style','');
    $p.find('.pa-event-card__title,.pa-event-card__title a,.pa-event-card__location,.pa-event-card__category-text,.pa-event-card__category-icon,.pa-event-card__meta-dot,.pa-event-card__description').attr('style','');
    $p.find('.pa-event-card__datebar,.pa-event-card__body').attr('style','');
    var bg=$('[name="agenda[background]"]').val(), accentBar=$('[name="agenda[accent_bar_color]"]').val(), titleCol=$('[name="agenda[title_color]"]').val() || $('[name="agenda[color]"]').val(), locCol=$('[name="agenda[location_color]"]').val(), bc=$('[name="agenda[border_color]"]').val(), tabBg=$('[name="agenda[tab_background_color]"]').val(), tabBorder=$('[name="agenda[tab_border_color]"]').val();
    if(bg){
      $p.css({'background-color':bg,'--pa-agenda-card-bg':bg});
      $p.find('.pa-event-card__body').css('background-color',bg);
    }
    if(accentBar){ $p.css('--pa-agenda-bar-color', accentBar); $p.find('.pa-event-card__datebar').css('background-color', accentBar); }
    if(titleCol){
      $p.css({'--pa-agenda-title-color':titleCol,'--pa-agenda-category-color':titleCol,'--pa-agenda-scroll-color':titleCol});
      $p.find('.pa-event-card__title,.pa-event-card__title a,.pa-event-card__category-text').css('color',titleCol);
    }
    if(locCol){
      $p.css({'--pa-agenda-location-color':locCol,'--pa-agenda-category-color':locCol,'--pa-agenda-scroll-color':locCol});
      $p.find('.pa-event-card__location,.pa-event-card__meta-dot,.pa-event-card__description').css('color',locCol);
    }
    applyProgramBorder($p,'agenda'); if(bc){ $p.css({'borderColor':bc,'--pa-event-card-border-color':bc}); }
    var borderMap={tl:'top-left',tr:'top-right',br:'bottom-right',bl:'bottom-left'};
    ['tl','tr','br','bl'].forEach(function(k){
      var v=$('[name="agenda[radius_'+k+']"]').val();
      if(v !== undefined && v !== ''){ $p[0].style.setProperty('border-'+borderMap[k]+'-radius', v+'px', 'important'); }
    });
    ['top','right','bottom','left'].forEach(function(k){
      var v=$('[name="agenda[width_'+k+']"]').val();
      if(v !== undefined && v !== ''){ $p[0].style.setProperty('border-'+k+'-width', v+'px', 'important'); $p[0].style.setProperty('border-style', 'solid', 'important'); }
    });
    if(bc){ $p[0].style.setProperty('border-color', bc, 'important'); }
    $p[0].style.setProperty('overflow','hidden','important');
    var cardSize=$('[name="agenda[card_size]"]').val() || 'full';
    cardSize = cardSize === 'thin' ? 'thin' : 'full';
    $('[name="agenda[card_size]"]').val(cardSize);
    $p.removeClass('pa-event-card--size-thin pa-event-card--size-full').addClass('pa-event-card--size-'+cardSize);
    $('.pa-event-card-preview-description').toggle(cardSize !== 'thin' && $('[name="agenda[show_descriptions]"]').val() !== 'hide');
    $p.addClass('pa-event-card--speakers-inline');
    $p.removeClass('pa-event-card--hover-default pa-event-card--hover-slant');
    var mode=$('[name="agenda[display_mode]"]').val();
    $tabs.prop('hidden', mode !== 'tabs');
    if(mode === 'tabs'){
      var effectiveTabBg = tabBg || bg;
      var $tabButtons = $tabs.find('.pa-agenda-day-tab, button');
      $tabs.attr('style','');
      if(effectiveTabBg) $tabs[0].style.setProperty('--pa-agenda-tab-bg', effectiveTabBg);
      if(titleCol) $tabs[0].style.setProperty('--pa-agenda-tab-color', titleCol);
      if(tabBorder) $tabs[0].style.setProperty('--pa-agenda-tab-border-color', tabBorder);

      ['top','right','bottom','left'].forEach(function(side){
        var v=$('[name="agenda[tab_border][width_'+side+']"]').val();
        if(v !== undefined && v !== ''){
          var parsed = parseInt(v,10) || 0;
          $tabs[0].style.setProperty('--pa-agenda-tab-border-width-'+side, parsed+'px');
        }
      });
      var radiusMap={tl:'tl',tr:'tr',br:'br',bl:'bl'};
      Object.keys(radiusMap).forEach(function(k){
        var v=$('[name="agenda[tab_border][radius_'+k+']"]').val();
        if(v !== undefined && v !== ''){ $tabs[0].style.setProperty('--pa-agenda-tab-radius-'+radiusMap[k], parseInt(v,10)+'px'); }
      });

      $tabButtons.removeClass('is-square').attr('style','');
    }
    var display=$('[name="agenda[date_display]"]').val();
    var sample=display==='abbrev'||display==='full'?'Aug. 20':'8/20';
    $('.pa-event-card-preview-date').text(sample);
    $tabs.find('button:first').text(sample);
  }
  $(document).on('change input','.pa-agenda-live-field,[name^="agenda"]',updateAgendaPreview); updateAgendaPreview();




  function setProgramPageBorder(root, group, data){
    data=data || {};
    var prefix=root+'['+group+']';
    $('[name="'+prefix+'[lock_radius]"]').prop('checked', !!data.lock_radius);
    $('[name="'+prefix+'[lock_width]"]').prop('checked', !!data.lock_width);
    ['tl','tr','br','bl'].forEach(function(k){
      var v=(data['radius_'+k] !== undefined && data['radius_'+k] !== '') ? data['radius_'+k] : '0';
      $('[name="'+prefix+'[radius_'+k+']"]').val(v);
    });
    ['top','right','bottom','left'].forEach(function(k){
      var v=(data['width_'+k] !== undefined && data['width_'+k] !== '') ? data['width_'+k] : '0';
      $('[name="'+prefix+'[width_'+k+']"]').val(v);
    });
    setColor($('[name="'+prefix+'[color]"]').closest('.pa-color-control'), data.color || '');
  }
  function setProgramPageSettings(root, settings){
    settings=settings || {};
    setColor($('[name="'+root+'[header_bg]"]').closest('.pa-color-control'), settings.header_bg || '');
    setColor($('[name="'+root+'[header_color]"]').closest('.pa-color-control'), settings.header_color || '');
    setColor($('[name="'+root+'[content_bg]"]').closest('.pa-color-control'), settings.content_bg || '');
    setColor($('[name="'+root+'[content_color]"]').closest('.pa-color-control'), settings.content_color || '');
    setProgramPageBorder(root, 'header_border', settings.header_border || {});
    setProgramPageBorder(root, 'content_border', settings.content_border || {});
    if($('[name="'+root+'[image_shape]"]').length){
      $('[name="'+root+'[image_shape]"]').val(settings.image_shape || '');
      $('[name="'+root+'[image_border_width]"]').val((settings.image_border_width !== undefined && settings.image_border_width !== '') ? settings.image_border_width : '0');
      setColor($('[name="'+root+'[image_border_color]"]').closest('.pa-color-control'), settings.image_border_color || '');
    }
  }

  $(document).on('click','.pa-reset-all-advanced',function(e){
    e.preventDefault();
    $('[name="agenda[show_filters]"]').prop('checked', true);
    $('[name="agenda[show_descriptions]"]').val('hide');
    $('[name="agenda[display_mode]"]').val('tabs');
    $('[name="agenda[speaker_layout]"]').val('inline');
    setColor($('[name="agenda[tab_background_color]"]').closest('.pa-color-control'),'');
    setColor($('[name="agenda[tab_border_color]"]').closest('.pa-color-control'),'');
    $('[name="agenda[tab_border][lock_radius]"]').prop('checked', true);
    $('[name="agenda[tab_border][lock_width]"]').prop('checked', true);
    ['tl','tr','br','bl'].forEach(function(k){ $('[name="agenda[tab_border][radius_'+k+']"]').val('999'); });
    ['top','right','bottom','left'].forEach(function(k){ $('[name="agenda[tab_border][width_'+k+']"]').val('1'); });
    $('[name="agenda[date_display]"]').val('numeric');
    $('[name="agenda[card_size]"]').val('full');
    setColor($('[name="agenda[background]"]').closest('.pa-color-control'),'');
    setColor($('[name="agenda[accent_bar_color]"]').closest('.pa-color-control'),'');
    setColor($('[name="agenda[title_color]"]').closest('.pa-color-control'),'');
    setColor($('[name="agenda[location_color]"]').closest('.pa-color-control'),'');
    setColor($('[name="agenda[border_color]"]').closest('.pa-color-control'),'');
    setProgramBorder('agenda', {});

    $('[name="speaker_card[show_thumbnail]"]').prop('checked', true);
    $('[name="speaker_card[thumbnail_shape]"]').val('theme');
    setColor($('[name="speaker_card[background]"]').closest('.pa-color-control'),'');
    setColor($('[name="speaker_card[color]"]').closest('.pa-color-control'),'');
    setColor($('[name="speaker_card[border_color]"]').closest('.pa-color-control'),'');
    setProgramBorder('speaker_card', {});

    setProgramPageSettings('event_page_settings', {});
    setProgramPageSettings('speaker_page_settings', {});

    updateAgendaPreview();
    updateSpeakerCardPreview();
    updateProgramPagePreviews();
  });

  $(document).on('click','.pa-copy-program-styles',function(e){
    e.preventDefault();
    var $opt=$('.pa-copy-program-source option:selected');
    if(!$opt.length || !$opt.val()) return;
    var styles=$opt.data('styles') || {};
    if(styles.categories && $('#pa-categories').length){
      var $wrap=$('#pa-categories').empty();
      var tpl=$('#pa-category-template').html();
      styles.categories.forEach(function(cat,i){
        var $row=$(tpl.replaceAll('__INDEX__',i));
        $row.find('input[name$="[name]"]').val(cat.name || '');
        setColor($row.find('.pa-color-control'), cat.color || '#000000');
        $row.find('.pa-category-icon-select').val(cat.icon || 'none');
        refreshCategoryIcon($row);
        $wrap.append($row);
      });
    }
    if(styles.speaker_card){
      var sc=styles.speaker_card;
      $('[name="speaker_card[show_thumbnail]"]').prop('checked', sc.show_thumbnail !== '0');
      $('[name="speaker_card[thumbnail_shape]"]').val(sc.thumbnail_shape || 'theme');
      setColor($('[name="speaker_card[background]"]').closest('.pa-color-control'), sc.background || '');
      setColor($('[name="speaker_card[color]"]').closest('.pa-color-control'), sc.color || '');
      setProgramBorder('speaker_card', sc);
      setColor($('[name="speaker_card[border_color]"]').closest('.pa-color-control'), sc.border_color || '');
      updateSpeakerCardPreview();
    }
    if(styles.agenda){
      var ag=styles.agenda;
      $('[name="agenda[show_descriptions]"]').val(ag.show_descriptions || 'show');
      $('[name="agenda[display_mode]"]').val(ag.display_mode === 'tabs' ? 'tabs' : 'stacked');
      $('[name="agenda[speaker_layout]"]').val('inline');
      var copiedCardSize = ag.card_size === 'thin' ? 'thin' : 'full';
      $('[name="agenda[card_size]"]').val(copiedCardSize);
      var dd=(ag.date_display==='abbrev'||ag.date_display==='numeric')?ag.date_display:(ag.date_display==='full'?'abbrev':'numeric');
      $('[name="agenda[date_display]"]').val(dd);
      setColor($('[name="agenda[background]"]').closest('.pa-color-control'), ag.background || '');
      setColor($('[name="agenda[accent_bar_color]"]').closest('.pa-color-control'), ag.accent_bar_color || '');
      setColor($('[name="agenda[title_color]"]').closest('.pa-color-control'), ag.title_color || ag.color || '');
      setColor($('[name="agenda[location_color]"]').closest('.pa-color-control'), ag.location_color || '');
      setColor($('[name="agenda[tab_background_color]"]').closest('.pa-color-control'), ag.tab_background_color || '');
      setColor($('[name="agenda[tab_border_color]"]').closest('.pa-color-control'), ag.tab_border_color || '');
      if(ag.tab_border){
        $('[name="agenda[tab_border][lock_radius]"]').prop('checked', !!ag.tab_border.lock_radius);
        $('[name="agenda[tab_border][lock_width]"]').prop('checked', !!ag.tab_border.lock_width);
        ['tl','tr','br','bl'].forEach(function(k){ $('[name="agenda[tab_border][radius_'+k+']"]').val((ag.tab_border['radius_'+k] !== undefined && ag.tab_border['radius_'+k] !== '') ? ag.tab_border['radius_'+k] : '999'); });
        ['top','right','bottom','left'].forEach(function(k){ $('[name="agenda[tab_border][width_'+k+']"]').val((ag.tab_border['width_'+k] !== undefined && ag.tab_border['width_'+k] !== '') ? ag.tab_border['width_'+k] : '1'); });
      }
      setProgramBorder('agenda', ag);
      setColor($('[name="agenda[border_color]"]').closest('.pa-color-control'), ag.border_color || '');
      updateAgendaPreview();
    }
    if(styles.event_page){
      $('.pa-copy-event-page-settings').val(JSON.stringify(styles.event_page || {}));
      setProgramPageSettings('event_page_settings', styles.event_page || {});
      updateProgramPagePreviews();
    }
    if(styles.speaker_page){
      $('.pa-copy-speaker-page-settings').val(JSON.stringify(styles.speaker_page || {}));
      setProgramPageSettings('speaker_page_settings', styles.speaker_page || {});
      updateProgramPagePreviews();
    }
  });

  $(document).off('click.paProgramEvents').on('click.paProgramEvents','.pa-program-events-toggle',function(e){
    e.preventDefault();
    var target=$(this).attr('data-target') || $(this).data('target');
    var $row=target ? $('#'+target) : $();
    if(!$row.length) return;
    var isOpen=$(this).attr('aria-expanded') === 'true';
    if(isOpen){
      $row.addClass('pa-is-hidden').attr('aria-hidden','true').css('display','none');
      $(this).attr('aria-expanded','false').removeClass('is-open');
    }else{
      $row.removeClass('pa-is-hidden').attr('aria-hidden','false').css('display','table-row');
      $(this).attr('aria-expanded','true').addClass('is-open');
    }
  });


  function toggleInviteWarningEditor(){
    var $toggle=$('.pa-invite-only-toggle');
    var $editor=$('.pa-invite-warning-editor');
    if(!$toggle.length || !$editor.length) return;
    $editor.prop('hidden', !$toggle.is(':checked'));
  }
  $(document).on('change','.pa-invite-only-toggle',toggleInviteWarningEditor);




  (function(){
    var storageKey = 'paDismissUnsavedProgressWarningSession';
    function storageAvailable(){
      try { window.sessionStorage.setItem('__pa_test','1'); window.sessionStorage.removeItem('__pa_test'); return true; }
      catch(e){ return false; }
    }
    var canStore = storageAvailable();
    if(canStore && window.sessionStorage.getItem(storageKey) === '1'){
      $('[data-pa-unsaved-warning]').prop('hidden', true).hide();
    }
    $(document).on('click', '.pa-dismiss-unsaved-warning', function(e){
      e.preventDefault();
      if(canStore){ window.sessionStorage.setItem(storageKey, '1'); }
      $('[data-pa-unsaved-warning]').prop('hidden', true).slideUp(120);
    });
  })();

  (function(){
    var isSubmittingEntityForm = false;
    var dirtyEntityForms = new WeakSet();
    var watchedSelector = '.pa-comfortable-form:not(.pa-program-advanced-only-form)';
    var warningMessage = 'If you leave this form before saving, your progress will be lost. Use Save as draft to keep an unfinished entry.';

    function markDirty(form){
      if(form){ dirtyEntityForms.add(form); }
    }
    function hasDirtyEntityForm(){
      var dirty = false;
      $(watchedSelector).each(function(){
        if(dirtyEntityForms.has(this)){
          dirty = true;
          return false;
        }
      });
      return dirty;
    }

    $(document).on('input change', watchedSelector + ' input, ' + watchedSelector + ' textarea, ' + watchedSelector + ' select', function(){
      markDirty($(this).closest('form').get(0));
    });

    $(document).on('click', watchedSelector + ' .button-primary, ' + watchedSelector + ' .pa-save-draft-link', function(){
      isSubmittingEntityForm = true;
    });

    $(document).on('submit', watchedSelector, function(){
      isSubmittingEntityForm = true;
    });

    $(window).on('beforeunload', function(e){
      if(isSubmittingEntityForm || !hasDirtyEntityForm()){
        return undefined;
      }
      e.preventDefault();
      e.returnValue = warningMessage;
      return warningMessage;
    });
  })();

  $(document).on('click', '[data-pa-delete-confirm]', function(e){
    var message = $(this).attr('data-pa-delete-confirm') || 'Are you sure you want to permanently delete this item? This cannot be undone.';
    if(!window.confirm(message)){
      e.preventDefault();
      return false;
    }
  });

  $(document).on('click', '.pa-form-actions .button-primary', function(){
    $(this).closest('form').find('input[name="pa_post_status"]').val('publish');
  });

  $(document).on('click', '.pa-save-draft-link', function(e){
    e.preventDefault();
    var form = $(this).closest('form').get(0);
    if(!form) return;
    $(form).find('input[name="pa_post_status"]').val('draft');
    if(form.requestSubmit){
      form.requestSubmit();
    }else{
      form.submit();
    }
  });

  toggleInviteWarningEditor();

});

// v1.15.112: sortable admin tables and independent bulk action/program/sponsor-level controls
jQuery(function($){
  function updateBulkCount($form){
    var count=$form.find('.pa-bulk-item-check:checked:visible').length;
    $form.find('.pa-bulk-count').text(count+' selected');
    var total=$form.find('.pa-bulk-item-check:visible').length;
    $form.find('.pa-bulk-check-all').prop('checked', total>0 && count===total);
  }

  function paReadSponsorLevelsFromOption($programSelect){
    var raw=$programSelect.find('option:selected').attr('data-sponsor-levels')||'';
    if(!raw){return [];}
    try{
      var parsed=JSON.parse(raw);
      return Array.isArray(parsed)?parsed.filter(Boolean):[];
    }catch(e){return [];}
  }

  function refreshBulkLevels($form){
    var $programSelect=$form.find('.pa-bulk-program-select');
    var pid=$programSelect.val();
    var levels=(window.paProgramAgenda&&paProgramAgenda.programSponsorLevels&&paProgramAgenda.programSponsorLevels[pid])?paProgramAgenda.programSponsorLevels[pid]:[];
    if(!levels.length){levels=paReadSponsorLevelsFromOption($programSelect);}
    var $level=$form.find('.pa-bulk-level-select');
    if(!$level.length){return;}
    $level.empty().append($('<option>').val('').text('Assign sponsor level'));
    levels.forEach(function(level){$level.append($('<option>').val(level).text(level));});
    $form.find('.pa-bulk-level-field').prop('hidden', !pid || levels.length===0);
  }
  $(document).on('change','.pa-bulk-program-select',function(){refreshBulkLevels($(this).closest('.pa-bulk-form'));});

  $(document).on('change','.pa-bulk-item-check,.pa-bulk-check-all',function(){
    var $form=$(this).closest('.pa-bulk-form');
    if($(this).hasClass('pa-bulk-check-all')){
      var checked=this.checked;
      $form.find('.pa-bulk-item-check:visible').prop('checked',checked);
    }
    updateBulkCount($form);
  });
  $(document).on('click','.pa-bulk-select-visible',function(e){
    e.preventDefault();
    var $form=$(this).closest('.pa-bulk-form');
    $form.find('.pa-bulk-item-check:visible').prop('checked',true);
    updateBulkCount($form);
  });
  $(document).on('click','.pa-bulk-clear',function(e){
    e.preventDefault();
    var $form=$(this).closest('.pa-bulk-form');
    $form.find('.pa-bulk-item-check').prop('checked',false);
    updateBulkCount($form);
  });
  $(document).on('submit','.pa-bulk-form',function(e){
    var $form=$(this), action=$form.find('.pa-bulk-action-select').val(), program=$form.find('.pa-bulk-program-select').val(), level=$form.find('.pa-bulk-level-select').val(), count=$form.find('.pa-bulk-item-check:checked').length;
    if(!count){ e.preventDefault(); alert('Select at least one item first.'); return; }
    if(level && !program){ e.preventDefault(); alert('Choose a program before assigning a sponsor level.'); return; }
    if(!action && !program && !level){ e.preventDefault(); alert('Choose at least one change: move to Draft, delete, assign a program, or assign a sponsor level.'); return; }
    if(action==='draft' && !confirm('Move the selected items to Draft?')){ e.preventDefault(); return; }
    if(action==='delete' && !confirm('Delete the selected items? This cannot be undone.')){ e.preventDefault(); }
  });

  $(document).on('input','.pa-admin-list-search',function(){
    var $form=$(this).closest('.pa-wrap').find('.pa-bulk-form');
    if($form.length){ setTimeout(function(){ updateBulkCount($form); },0); }
  });

  $(document).on('click','.pa-sort-button',function(e){
    e.preventDefault();
    var $button=$(this), $th=$button.closest('th'), $table=$th.closest('table'), index=$th.index(), type=$button.data('pa-sort-type')||'text';
    var current=$th.attr('data-pa-sort-dir')==='asc'?'desc':'asc';
    $table.find('th').removeAttr('data-pa-sort-dir').find('.pa-sort-indicator').text('↕');
    $th.attr('data-pa-sort-dir',current).find('.pa-sort-indicator').text(current==='asc'?'↑':'↓');
    var rows=$table.find('tbody tr.pa-searchable-row').get();
    rows.sort(function(a,b){
      var av=$(a).children('td').eq(index).attr('data-pa-sort-value')||$(a).children('td').eq(index).text();
      var bv=$(b).children('td').eq(index).attr('data-pa-sort-value')||$(b).children('td').eq(index).text();
      if(type==='number'){ av=parseFloat(av)||0; bv=parseFloat(bv)||0; }
      else { av=String(av).toLowerCase(); bv=String(bv).toLowerCase(); }
      if(av<bv){ return current==='asc'?-1:1; }
      if(av>bv){ return current==='asc'?1:-1; }
      return 0;
    });
    $.each(rows,function(_,row){
      var $row=$(row), key=$row.attr('data-pa-row-key');
      $table.children('tbody').append(row);
      if(key){
        var $detail=$table.find('tbody tr[data-pa-detail-for="'+key+'"]');
        if($detail.length){ $table.children('tbody').append($detail); }
      }
    });
    $table.find('tbody').append($table.find('tbody tr.pa-list-search-empty'));
  });

  $('.pa-bulk-form').each(function(){ updateBulkCount($(this)); refreshBulkLevels($(this)); });
});

// v1.15.115: searchable multi-program sponsor assignment with per-program levels
jQuery(function($){
  function sponsorProgramLevels(programId){
    var all=(window.paProgramAgenda&&paProgramAgenda.programSponsorLevels)||{};
    return (programId && all[programId]) ? all[programId] : [];
  }
  function selectedSponsorProgramIds(){
    var ids=[];
    $('.pa-selected-sponsor-programs li').each(function(){ids.push(String($(this).data('id')));});
    return ids;
  }
  function sponsorProgramName(programId){
    var $label=$('.pa-sponsor-program-check[value="'+programId+'"]').closest('label');
    return $.trim($label.text()) || ('Program '+programId);
  }
  function buildSponsorProgramRow(programId){
    programId=String(programId);
    if($('.pa-selected-sponsor-programs li[data-id="'+programId+'"]').length){return;}
    var levels=sponsorProgramLevels(programId);
    var $li=$('<li>').attr('data-id',programId);
    var $head=$('<div>').addClass('pa-sponsor-program-row-head').append($('<strong>').text(sponsorProgramName(programId)));
    $('<button type="button">').addClass('button-link pa-remove-sponsor-program').text('Remove').appendTo($head);
    $li.append($head);
    var $levels=$('<div>').addClass('pa-sponsor-program-levels').attr('data-program-id',programId);
    if(levels.length){
      $levels.append($('<span>').addClass('pa-sponsor-program-level-heading').text('Assign sponsor level'));
      levels.forEach(function(level){
        var id='pa-sponsor-program-'+programId+'-level-'+String(level).toLowerCase().replace(/[^a-z0-9]+/g,'-');
        var $label=$('<label>').attr('for',id);
        $('<input type="checkbox">').attr({id:id,name:'sponsor_program_levels['+programId+'][]',value:level}).appendTo($label);
        $label.append(' '+level).appendTo($levels);
      });
    }else{
      $levels.append($('<p>').addClass('description').text('No sponsor levels exist for this Program yet.'));
    }
    $li.append($levels).appendTo($('.pa-selected-sponsor-programs'));
  }
  $(document).on('input','.pa-sponsor-program-search',function(){
    var q=String($(this).val()||'').toLowerCase();
    $('.pa-sponsor-program-picker label').each(function(){
      var terms=String($(this).data('name')||'').toLowerCase();
      $(this).toggle(terms.indexOf(q)!==-1);
    });
  });
  $(document).on('change','.pa-sponsor-program-check',function(){
    var id=String($(this).val());
    if(this.checked){ buildSponsorProgramRow(id); }
    else { $('.pa-selected-sponsor-programs li[data-id="'+id+'"]').remove(); }
  });
  $(document).on('click','.pa-remove-sponsor-program',function(e){
    e.preventDefault();
    var id=String($(this).closest('li').data('id'));
    $('.pa-sponsor-program-check[value="'+id+'"]').prop('checked',false);
    $(this).closest('li').remove();
  });
  $(document).on('click','.pa-select-all-sponsor-programs',function(e){
    e.preventDefault();
    $('.pa-sponsor-program-picker label:visible .pa-sponsor-program-check').each(function(){
      if(!this.checked){$(this).prop('checked',true).trigger('change');}
    });
  });
});
