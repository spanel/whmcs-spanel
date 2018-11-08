<form action="clientarea.php" method="post" target="_blank">
    <input type="hidden" name="action" value="productdetails" />
    <input type="hidden" name="id" value="{$serviceid}" />
    <input type="hidden" name="dosinglesignon" value="1" />
    <input type="submit" value="{if $producttype=="hostingaccount"}Login to Spanel{else}Login to Spanel (admin){/if}" class="btn btn-primary modulebutton" />
</form>
