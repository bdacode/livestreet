{$oValue = $oProperty->getValue()}
{$oValueType = $oValue->getValueTypeObject()}

{include file="forms/fields/form.field.file.tpl"
		 sFieldName    = "property[{$oProperty->getId()}][file]"
		 sFieldClasses = 'width-300'
		 sFieldNote = $oProperty->getDescription()
		 sFieldLabel   = $oProperty->getTitle()}

{$aFile=$oValue->getDataOne('file')}
{if $aFile}
	<a href="{$oValueType->getImageWebPath()}" class="js-lbx" target="_blank"><img src="{$oValueType->getImageWebPath($oValueType->getImageSizeFirst())}" ></a> <br/>
	<label>
		<input type="checkbox" name="property[{$oProperty->getId()}][remove]" value="1"> &mdash; удалить изображение
	</label>
	<br/>
{/if}