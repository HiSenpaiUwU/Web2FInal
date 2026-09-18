<?php
// Repeat-safe demo dataset. It uses the application's existing tables and never deletes data.
if (!function_exists('seed_demo_businesses')) {
function seed_demo_businesses(PDO $pdo): int {
    // Older versions generated synthetic coordinates. Demo records are intentionally left unmapped
    // until an owner/admin supplies a real Davao City location.
    $pdo->exec("UPDATE businesses SET latitude=NULL, longitude=NULL WHERE contact_email LIKE '%@demo.local' AND address LIKE 'Demo Address,%'");
    $ownerEmail = 'demo.owner@davaolocal.test';
    $customerEmail = 'demo.customer@davaolocal.test';
    $pdo->prepare('INSERT IGNORE INTO users(role_id,name,email,password_hash) VALUES(2,?,?,?)')->execute(['Davao Demo Owner',$ownerEmail,password_hash('DemoOwner123!', PASSWORD_DEFAULT)]);
    $pdo->prepare('INSERT IGNORE INTO users(role_id,name,email,password_hash) VALUES(1,?,?,?)')->execute(['Demo Customer',$customerEmail,password_hash('DemoCustomer123!', PASSWORD_DEFAULT)]);
    $q=$pdo->prepare('SELECT id FROM users WHERE email=?');$q->execute([$ownerEmail]);$owner=(int)$q->fetchColumn();$q->execute([$customerEmail]);$customer=(int)$q->fetchColumn();
    $data = [
      ['Davao QuickPrint','Printing Services','Same-day printing for school, work, and events.','Bajada','Poblacion','Document printing,Photocopy,Lamination,Binding,ID printing','08:00','19:00'],
      ['CityPrint Hub','Printing Services','Affordable print and layout services for the community.','Agdao','Poblacion','Tarpaulin printing,Stickers,Invitations,Photocopy','09:00','18:00'],
      ['Ink & Paper Davao','Printing Services','Creative print solutions for projects and celebrations.','Matina','Talomo','Thesis printing,Document printing,Lamination,Binding','08:00','20:00'],
      ['PrintPoint Services','Printing Services','Convenient neighborhood printing and scanning service.','Buhangin','Buhangin','Photocopy,Scanning,ID printing,Document printing','08:30','18:30'],
      ['Davao Street Eats','Food & Beverage','Comfort food and grilled favorites served fresh daily.','Ecoland','Talomo','Grilled meals,Rice meals,Takeout,Catering','10:00','22:00'],
      ['Local Bites Food Stall','Food & Beverage','Quick, affordable merienda and lunch meals.','Sasa','Buhangin','Snacks,Rice meals,Delivery,Party trays','07:00','19:00'],
      ["Nanay's Homemade Meals",'Food & Beverage','Home-cooked Filipino dishes for busy families.','Bangkal','Talomo','Daily ulam,Family trays,Pre-order meals,Delivery','06:00','18:00'],
      ['Corner Grill House','Food & Beverage','Casual grill house for friends and family.','Toril','Toril','Grilled chicken,Pork barbecue,Seafood,Takeout','11:00','23:00'],
      ['Bahay Kape Café','Food & Beverage','A quiet neighborhood coffee shop with local beans.','Maa','Talomo','Espresso,Coffee beans,Pastries,Study space','07:00','21:00'],
      ['Fresh Cup Coffee','Food & Beverage','Iced coffee and made-to-order drinks.','Poblacion','Poblacion','Cold brew,Milk tea,Pastries,Delivery','08:00','20:00'],
      ['Morning Brew Davao','Food & Beverage','Fresh coffee for early risers and remote workers.','Bajada','Poblacion','Espresso,Breakfast,Wi-Fi,Takeout','06:30','19:00'],
      ['QuickFix Electronics','Electronics','Dependable repairs for household electronics.','Agdao','Poblacion','Diagnostics,TV repair,Appliance repair,Parts replacement','09:00','18:00'],
      ['Davao Phone Repair','Electronics','Fast smartphone diagnostics and repair.','Buhangin','Buhangin','Screen replacement,Battery replacement,Software troubleshooting,Diagnostics','09:00','19:00'],
      ['Local Gadget Repair','Electronics','Repairs and accessories for phones and tablets.','Matina','Talomo','Phone repair,Tablet repair,Charging port repair,Accessories','10:00','19:00'],
      ['Tech Rescue Davao','Electronics','Computer and laptop support for home users.','Ecoland','Talomo','Laptop repair,Data recovery,Software installation,Hardware repair','09:00','18:00'],
      ['QuickMoto Repair','Repair Services','Honest motorcycle maintenance and tune-ups.','Calinan','Calinan','Oil change,Tune-up,Brake service,Electrical repair','08:00','17:00'],
      ['RoadReady Auto Shop','Automotive','Practical car care and preventive maintenance.','Bunawan','Bunawan','Change oil,Wheel alignment,Engine scan,Brake service','08:00','18:00'],
      ['Local Tire & Repair','Automotive','Tires, vulcanizing, and roadside assistance.','Sasa','Buhangin','Tire repair,Vulcanizing,Tire replacement,Balancing','07:00','19:00'],
      ['Bella Home Salon','Beauty & Wellness','Relaxed, friendly salon for everyday beauty care.','Maa','Talomo','Haircut,Hair coloring,Hair treatment,Manicure,Pedicure','10:00','20:00'],
      ['Davao Style Studio','Beauty & Wellness','Modern cuts and styling for every occasion.','Bajada','Poblacion','Haircut,Makeup,Hair styling,Facial','09:00','20:00'],
      ['Glow Beauty Corner','Beauty & Wellness','Simple self-care services in a welcoming space.','Bangkal','Talomo','Facial,Manicure,Pedicure,Eyelash service','10:00','19:00'],
      ['Fresh Cut Barbershop','Beauty & Wellness','Classic and modern cuts for the whole family.','Tugbok','Tugbok','Haircut,Beard trim,Hair wash,Kids haircut','09:00','20:00'],
      ['Local Threads Davao','Clothing','Everyday clothing and locally made pieces.','Poblacion','Poblacion','Casual wear,Custom shirts,Alterations,Gift wrapping','10:00','20:00'],
      ['Davao Custom Prints','Clothing','Personalized shirts, uniforms, and merchandise.','Buhangin','Buhangin','Shirt printing,Uniforms,Embroidery,Custom mugs','09:00','18:00'],
      ['Davao Alterations','Clothing','Careful tailoring and clothing repairs.','Matina','Talomo','Hemming,Resizing,Uniform repair,Custom tailoring','08:00','17:00'],
      ['Davao Home Repair','Home Services','Small home repairs done with care.','Toril','Toril','Carpentry,Door repair,Painting,Minor renovations','08:00','17:00'],
      ['CleanHome Davao','Home Services','Flexible residential cleaning appointments.','Ecoland','Talomo','Deep cleaning,Move-in cleaning,Office cleaning,Laundry help','08:00','18:00'],
      ['Local Plumbing Services','Home Services','Responsive plumbing support for homes and rentals.','Agdao','Poblacion','Leak repair,Pipe installation,Drain cleaning,Fixture repair','08:00','17:00'],
      ['Davao Electrical Services','Home Services','Licensed-style household electrical assistance.','Bunawan','Bunawan','Wiring,Outlet repair,Light installation,Electrical inspection','08:00','17:00'],
      ['Davao Tutorial Hub','Education','Friendly tutors for school subjects and review sessions.','Maa','Talomo','Math tutoring,English tutoring,Science tutoring,Study coaching','13:00','20:00'],
      ['Home Tutor Davao','Education','One-on-one learning support at flexible times.','Calinan','Calinan','Elementary tutoring,High school tutoring,Online tutoring,Homework help','09:00','18:00'],
      ['Neighborhood Convenience','Retail','Daily essentials, prepaid load, and quick snacks.','Sasa','Buhangin','Groceries,Prepaid load,Drinks,Home delivery','06:00','22:00'],
      ['Gadget Corner Davao','Electronics','Affordable accessories and basic gadget care.','Bajada','Poblacion','Phone cases,Chargers,Earphones,Screen protectors','10:00','20:00'],
      ['LightFrame Photography','Professional Services','Portrait, product, and small event photography.','Matina','Talomo','Portrait photos,Product photos,Event coverage,Photo editing','09:00','18:00'],
      ['Gather & Celebrate Events','Professional Services','Simple, memorable event styling for local celebrations.','Poblacion','Poblacion','Event styling,Backdrop rental,Party coordination,Sound system rental','09:00','18:00'],
      ['Tugbok Computer Clinic','Electronics','Practical computer service for students and families.','Tugbok','Tugbok','Computer repair,OS installation,Data backup,Printer setup','09:00','18:00'],
      ['Calinan Appliance Fix','Repair Services','Home appliance inspection and repair service.','Calinan','Calinan','Fan repair,Rice cooker repair,Appliance diagnostics,Parts replacement','08:00','17:00'],
      ['Toril Fresh Market','Retail','Local produce and pantry goods for everyday cooking.','Toril','Toril','Fresh produce,Pantry goods,Delivery,Pre-order baskets','06:00','18:00'],
      ['Bangkal Bike Works','Repair Services','Bicycle tune-ups and essential parts.','Bangkal','Talomo','Bike tune-up,Brake adjustment,Tire replacement,Chain service','08:00','18:00'],
      ['Buhangin Pet Care','Other','Caring services and supplies for companion animals.','Buhangin','Buhangin','Pet grooming,Pet supplies,Nail trim,Pet sitting','09:00','18:00'],
    ];
    $categories = array_column($pdo->query('SELECT id,name FROM categories')->fetchAll(), 'id', 'name');
    $added=0; $n=1;
    foreach($data as $row){[$name,$category,$description,$barangay,$district,$serviceList,$opens,$closes]=$row; if(!isset($categories[$category])){$pdo->prepare('INSERT INTO categories(name) VALUES(?)')->execute([$category]);$categories[$category]=(int)$pdo->lastInsertId();}$check=$pdo->prepare('SELECT id FROM businesses WHERE name=? LIMIT 1');$check->execute([$name]);if($check->fetchColumn())continue;
      $status=$n%11===0?'pending':'approved';$lat=null;$lng=null;
      $pdo->prepare('INSERT INTO businesses(owner_id,category_id,name,description,contact_phone,contact_email,address,barangay,district,city,latitude,longitude,status,verified_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$owner,$categories[$category],$name,$description,'0917 555 '.str_pad((string)(1100+$n),4,'0',STR_PAD_LEFT),strtolower(str_replace([' ','&'],['',''],$name)).'@demo.local','Demo Address, '.$barangay,$barangay,$district,'Davao City',$lat,$lng,$status,$status==='approved'?date('Y-m-d H:i:s'):null]);
      $id=(int)$pdo->lastInsertId();foreach(explode(',',$serviceList) as $service){$service=trim($service);$pdo->prepare('INSERT IGNORE INTO services(name) VALUES(?)')->execute([$service]);$x=$pdo->prepare('SELECT id FROM services WHERE name=?');$x->execute([$service]);$pdo->prepare('INSERT INTO business_services(business_id,service_id) VALUES(?,?)')->execute([$id,$x->fetchColumn()]);}
      for($day=0;$day<7;$day++)$pdo->prepare('INSERT INTO business_hours(business_id,day_of_week,opens,closes,is_closed) VALUES(?,?,?,?,?)')->execute([$id,$day,$opens,$closes,$day===0]);
      if($status==='approved'){$pdo->prepare('INSERT INTO reviews(business_id,user_id,rating,comment) VALUES(?,?,?,?)')->execute([$id,$customer,($n%2?5:4),$n%2?'Fast service and good quality. Great local option.':'Affordable, convenient, and friendly service.']);}
      $added++;$n++;
    }
    return $added;
}}

if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    require __DIR__ . '/config.php';
    try { $count=seed_demo_businesses(db()); echo '<h1>Demo businesses added</h1><p>'.$count.' new records were added. Existing records were kept.</p><p><a href="index.php?page=directory">View directory</a></p>'; }
    catch(Throwable $e){http_response_code(500);echo '<h1>Could not seed data</h1><p>'.e($e->getMessage()).'</p><p>Start MySQL and run setup.php first.</p>';}
}
