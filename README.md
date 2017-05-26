# WP Template Wrapper

A better way to organize Wordpress templates.

## Features

- [Organized Template Files](#template-files)
- [Template Based Routing](#routes)
- [Template Includes](#template-includes)
- [Template Data](#template-data)
- [AJAX Routes](#ajax-routes)


<a name="template-files"></a>
## Organized Template Files

All the template files reside in the `templates` folder.  
Any template files at the theme's root folder will not work  


---
### Template Slug

Template Wrapper transforms wordpress's default template file naming to provide better organization of the files.  
'Slugged' template files, i.e. `single-{post_type|post_name|post_id}` etc., are grouped in a subdirectory named to that type.

> ***Example:***
> - `templates/single/post.php` will be used in place of `single-post.php`.
> - `templates/page/about-us.php` will be used in place of `page-about-us.php`.
> - `templates/archive/4.php` will be used in place of `archive-4.php`.
> - Template-type files will still be used as is; e.g., `templates/single.php`, `templates/page.php`, etc.

In general, files are organized by `{type}/{slug|id|custom-name}.php` format.


---
### Custom Page Templates

Custom page templates should be placed in the `templates` folder.

Template Wrapper uses custom page template files **only for registration** in the admin.  
That is, custom page template files will not be used as "views".  
You have to create a file of the same name in the `templates/page` folder.

> ***Example:***  
> To use a custom "Donate Page Template" using `custom-donate-page.php`,  
> Create one in the `page-templates` folder as a registry, containing only the template name.  
> Create another one in the `templates/page` folder as a "view". This will be the one rendering your view.



<a name="routes"></a>
## Template Based Routing

Template Wrapper allows route registration through `template_routes` hook.  
Routes are placed in `src/routes.php` in the following manner:

```php
// src/routes.php

add_filter( 'template_routes', function () {

	return array(
		$template_slug => $callable,
	);

});
```

**$template_slug** *(String)*  
> The "route" to the template slug. This is similar to the Wordpress's template hierarchy, though transformed to become directory-based.

**$callable** *(Function or Object/Class Method)*  
> The "controller". This will handle the data passed to the template. This can be either a function or an object/class method, similar to MVC approach.  
  
---

***Example:***
> ```
>	// Matches is_single(), using a function name
>	'single' => 'post_data',
>	// Matches 'project' post type single, Using a static class method
>	'single/project' => [ 'MyControllerClass', 'project_single' ],
>	// Matches single for a post_id of 12
>	'single/12' => [ 'MyControllerClass', 'project_single' ],
>	// Will match about page, using a namespaced class method
>	'page/about' => [ '\\MyNamespace\\AnotherController', 'about' ],
>	// Will match a custom page template registered in `page-templates/custom-page.php`
>	'page/custom-page' => [ 'MyControllerClass', 'custom_page_data' ],
> 	// Will match any page or single which is not registered in route
>	'page' => 'any_page_data',
>	'single' => 'any_single_data',
> ```
> ***Note:***  
> A "slugged" route doesnt require a template counterpart. Just like wordpress's template hierarchy, it will use the available template for that slug.
> That is, a `page/about` route will use `templates/page.php` if `templates/page/about.php` is not available.






<a name="template-includes"></a>
## Template Includes

The Template Wrapper provides a way to include templates and passing data through `include_template` method of the `Wrapper` class.  

```php
// single.php

use QtGye\TemplateWrapper\Wrapper;

<h1>This is an awesome post!</h1>

<?php
	Wrapper::include_template( 'modules/content', $content_data );
?>
```

To avoid having to declare Wrapper's namespace in every template, it is recommended to wrap it in a global function instead, like so:
```php
// functions.php

function include_template ( $template_slug ='', $data = [] ) {
	QtGye\TemplateWrapper\Wrapper::include_template( $template_slug, $data );
}

```

**$template_slug** *(String)*  
> The slug of the template file relative to the templates folder. In the example, it will load `templates/modules/content.php`.

**$data** *(Assoc. Array)*  
> The data to be passed into the included template. 
> Note that user-defined globals are also available in the included templates,
> so if there are common variable names, the template data will override it.





<a name="template-data"></a>
## Template Data

The route callable handles the data being passed to the template. 
Through **reflection**, the callable may receive user-defined globals as arguments.

***Example:***
> ```php
> class MyControllerClass {
>	
>	// Using the $post global
>	static function about_page ( $post ) {
>
>		$data = [];
>
>		$data['id'] = $post->ID;
>		$data['slug'] = $post->name;
>
>		// The variables $id and $slug are now available in the template
>		return $data;
> 
>	}
> }
> ```

---
### Global Data

The user-defined global data is defined in the `src/global.php` through the `template_global_data` hook. These globals are available within templates.  

***Example:***
> ```php
> // src/global.php
>
> add_filter('template_global_data', function ( $predefined_globals )
> {
>	// For some reason, use $wp_roles global
>	global $wp_roles;
>	
>	// $wp_roles variable is now ready to be used in the templates
>	return compact('wp_roles');
>});
> ```

Template Wrapper provides pre-defined globals to which user-defined globals are merged:  
**$wp, $wpdb, $wp_query, $post, $authordata, $page;**






<a name="ajax-routes"></a>
## AJAX Routes

Template Wrapper provides a way to handle ajax routing using action.  


Register ajax routes through the `ajax_routes` hook.  
> ```php
>	// src/routes.php
>
>	add_action( 'ajax_routes', function () {
>
>		return array(
>			// Matches ?action=items
>			'items' => [ "MyController", "ajax" ],
>		);
>
>	});
> ```

Within the ajax callback, GET parameters and user-defined globals are passed through **reflection**.  

> ```php
> class MyController {
>	
>	// Handling ?action=items&filter=all&page=3
>	// The argument names should match the GET parameter names. Hence, order is irrelevant.
>	static function ajax ( $page, $filter, $post ) {
>		
>		// $filter === 'all'
>		// $page === '3'
>		$response = [
>			'items' => get_filtered_posts( $filter, $page),
>			'current_id' => $post->ID,
>		];
>		
>		// Returned data is sent as json-encoded response.
>		return $response;
> 
>	}
> }
> ```


