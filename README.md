#Reflect

iReflect hooks into the sendfile mechanism (ATTENTION: this needs a hook in the fetch.php - described in the action.php) and creates an reflected imageversion which is then delivered. The modified file will be stored as a cache file.
            

##URL - params

  * bgc - [BGColor]
  * fade_start - [beginn of fading in %]
  * fade_end - [end of fading in %]
  * return_type - (png|jpg) [default set via config];
            
Originaly based on the reflect.php - modified by [imageflow](http://194.95.111.244/~countzero/myCMS/index.php?c_id=5&s_id=21), using the reflection routine from [here](http://de2.php.net/manual/en/function.imagealphablending.php#83282)
